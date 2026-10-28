<?php

declare(strict_types=1);

namespace Modules\Payment\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Support\Money;
use Modules\Payment\Contracts\CodRefundPayout;
use Modules\Payment\DTO\RefundResult;
use Modules\Payment\Events\RefundFailed;
use Modules\Payment\Events\RefundProcessed;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Exceptions\RefundException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Support\PaymentGatewayManager;

/**
 * Refunds (FR-PAY-006, plan M11 §5.4), used by cancellations (M12/M15) and returns (M15).
 *
 * - Idempotent: the caller stores a UUID per return / cancellation and passes it as the
 *   idempotency key; the same key returns the existing refund instead of refunding twice.
 * - Guarded: Σ pending + processing + processed refunds never exceeds the paid amount
 *   (checked under a lock on the payment row).
 * - Prepaid refunds go back to source through the gateway ("processing" until the
 *   refund.processed webhook). COD refunds are paid out to a bank account or UPI id by the
 *   CodRefundPayout (M17); until then they stay "pending" for Finance to mark paid.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly CodRefundPayout $payouts,
    ) {}

    public static function newIdempotencyKey(): string
    {
        return (string) Str::uuid();
    }

    /**
     * @param  array<string, string>|null  $beneficiary  COD only: ['account_number', 'ifsc', 'name'] or ['vpa']
     *
     * @throws RefundException
     */
    public function refund(
        Payment $payment,
        Money $amount,
        string $mode,
        ?array $beneficiary,
        string $reason,
        string $idempotencyKey,
        ?int $returnRequestId = null,
        ?int $salesOrderItemId = null,
    ): PaymentRefund {
        $existing = $this->findByKey($idempotencyKey);

        if ($existing !== null) {
            return $this->sameRefund($existing, $payment);
        }

        $this->validateRequest($payment, $amount, $mode, $beneficiary, $idempotencyKey);

        $refund = DB::transaction(function () use ($payment, $amount, $mode, $beneficiary, $reason, $idempotencyKey, $returnRequestId, $salesOrderItemId): PaymentRefund {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            // A concurrent call with the same key may have committed while we waited for the lock.
            if (($existing = $this->findByKey($idempotencyKey)) !== null) {
                return $this->sameRefund($existing, $payment);
            }

            $this->guardAmount($locked, $amount);

            return PaymentRefund::query()->create([
                'payment_id' => $locked->id,
                'return_request_id' => $returnRequestId,
                'sales_order_item_id' => $salesOrderItemId,
                'amount' => $amount,
                'mode' => $mode,
                'status' => PaymentRefund::STATUS_PENDING,
                'beneficiary' => $locked->isCod() ? $beneficiary : null,
                'reason' => mb_substr($reason, 0, 255),
                'idempotency_key' => $idempotencyKey,
            ]);
        });

        return $refund->wasRecentlyCreated ? $this->execute($refund) : $refund;
    }

    /**
     * What can still be refunded on a payment.
     */
    public function refundableAmount(Payment $payment): Money
    {
        if (! $payment->isRefundable()) {
            return Money::zero();
        }

        $remaining = $payment->amount->minus($this->committed($payment));

        return $remaining->isNegative() ? Money::zero() : $remaining;
    }

    /**
     * Send a pending refund to the gateway (prepaid) or the payout rail (COD).
     */
    public function execute(PaymentRefund $refund): PaymentRefund
    {
        $refund->loadMissing('payment');

        if ($refund->status !== PaymentRefund::STATUS_PENDING || $refund->payment === null) {
            return $refund;
        }

        if ($refund->payment->isCod()) {
            $payoutId = $this->payouts->payout($refund);

            if ($payoutId !== null) {
                DB::transaction(function () use ($refund, $payoutId): void {
                    $refund->payout_id = $payoutId;
                    $refund->transitionTo(PaymentRefund::STATUS_PROCESSING, null, ['payout_id' => $payoutId]);
                });
            }

            return $refund;
        }

        try {
            $result = $this->gateways->for($refund->payment)->refund($refund);
        } catch (GatewayException $e) {
            // The gateway may or may not have the refund: keep it pending, Finance retries.
            report($e);
            $refund->forceFill(['failure_reason' => mb_substr('Gateway unavailable: '.$e->getMessage(), 0, 255)])->save();

            return $refund;
        }

        return $this->applyResult($refund, $result);
    }

    /**
     * The gateway's answer (immediate response or refund.processed / refund.failed webhook).
     */
    public function applyResult(PaymentRefund $refund, RefundResult $result): PaymentRefund
    {
        return match ($result->status) {
            PaymentRefund::STATUS_PROCESSED => $this->markProcessed($refund, null, null, $result->gatewayRefundId),
            PaymentRefund::STATUS_FAILED => $this->markFailed($refund, $result->error ?? 'Refund failed', null, $result->gatewayRefundId),
            default => $this->markProcessing($refund, $result->gatewayRefundId),
        };
    }

    /**
     * Final success: the payment's refunded total and status follow; RefundProcessed fires.
     * Idempotent for an already processed refund.
     */
    public function markProcessed(PaymentRefund $refund, ?string $utr = null, ?Model $actor = null, ?string $gatewayRefundId = null): PaymentRefund
    {
        DB::transaction(function () use ($refund, $utr, $actor, $gatewayRefundId): void {
            $locked = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($locked->status === PaymentRefund::STATUS_PROCESSED) {
                return;
            }

            if (! $locked->canTransitionTo(PaymentRefund::STATUS_PROCESSED)) {
                throw new RefundException(trans('payment::payment_refund.messages.invalid_transition', ['status' => $locked->status]), 'invalid_transition');
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($locked->payment_id);

            $locked->utr = $utr ?? $locked->utr;
            $locked->gateway_refund_id = $gatewayRefundId ?? $locked->gateway_refund_id;
            $locked->processed_at = Carbon::now();
            $locked->failure_reason = null;
            $locked->transitionTo(PaymentRefund::STATUS_PROCESSED, null, $utr === null ? [] : ['utr' => $utr], $actor);

            $refunded = $payment->amount_refunded->plus($locked->amount);
            $payment->amount_refunded = $refunded;
            $target = $refunded->compare($payment->amount) >= 0 ? Payment::STATUS_REFUNDED : Payment::STATUS_PARTIALLY_REFUNDED;

            if ($payment->canTransitionTo($target)) {
                $payment->transitionTo($target, null, ['refund_id' => $locked->id], $actor);
            } else {
                $payment->save();
            }

            RefundProcessed::dispatch($locked);
        });

        return $refund->refresh();
    }

    /**
     * The refund failed (gateway or Finance). It no longer holds part of the refundable
     * amount; RefundFailed fires so Finance can act.
     */
    public function markFailed(PaymentRefund $refund, string $reason, ?Model $actor = null, ?string $gatewayRefundId = null): PaymentRefund
    {
        DB::transaction(function () use ($refund, $reason, $actor, $gatewayRefundId): void {
            $locked = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);

            if (! $locked->canTransitionTo(PaymentRefund::STATUS_FAILED)) {
                return;
            }

            $locked->failure_reason = mb_substr($reason, 0, 255);
            $locked->gateway_refund_id = $gatewayRefundId ?? $locked->gateway_refund_id;
            $locked->transitionTo(PaymentRefund::STATUS_FAILED, $reason, [], $actor);

            RefundFailed::dispatch($locked);
        });

        return $refund->refresh();
    }

    /**
     * Admin retry: a failed refund becomes pending again (guard re-checked), then a pending
     * one is sent again.
     *
     * @throws RefundException
     */
    public function retry(PaymentRefund $refund, ?Model $actor = null): PaymentRefund
    {
        if ($refund->status === PaymentRefund::STATUS_FAILED) {
            DB::transaction(function () use ($refund, $actor): void {
                $payment = Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
                $locked = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);

                if ($locked->status !== PaymentRefund::STATUS_FAILED) {
                    return;
                }

                $this->guardAmount($payment, $locked->amount);
                $locked->failure_reason = null;
                $locked->gateway_refund_id = null;
                $locked->transitionTo(PaymentRefund::STATUS_PENDING, 'retry', [], $actor);
            });

            $refund->refresh();
        }

        if ($refund->status !== PaymentRefund::STATUS_PENDING) {
            throw new RefundException(trans('payment::payment_refund.messages.not_retryable'), 'not_retryable');
        }

        return $this->execute($refund);
    }

    protected function markProcessing(PaymentRefund $refund, ?string $gatewayRefundId): PaymentRefund
    {
        DB::transaction(function () use ($refund, $gatewayRefundId): void {
            $locked = PaymentRefund::query()->lockForUpdate()->findOrFail($refund->id);
            $locked->gateway_refund_id = $gatewayRefundId ?? $locked->gateway_refund_id;
            $locked->failure_reason = null;

            if ($locked->canTransitionTo(PaymentRefund::STATUS_PROCESSING)) {
                $locked->transitionTo(PaymentRefund::STATUS_PROCESSING, null, ['gateway_refund_id' => $locked->gateway_refund_id]);
            } else {
                $locked->save();
            }
        });

        return $refund->refresh();
    }

    /**
     * @throws RefundException
     */
    protected function guardAmount(Payment $payment, Money $amount): void
    {
        if (! $payment->isRefundable()) {
            throw new RefundException(trans('payment::payment_refund.messages.not_refundable'), 'not_refundable');
        }

        $available = $payment->amount->minus($this->committed($payment));

        if ($amount->compare($available) > 0) {
            throw new RefundException(trans('payment::payment_refund.messages.over_refund', ['available' => ($available->isNegative() ? Money::zero() : $available)->format(true)]), 'over_refund');
        }
    }

    /**
     * Σ refunds that hold money (pending, processing, processed).
     */
    protected function committed(Payment $payment): Money
    {
        $total = PaymentRefund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', PaymentRefund::ACTIVE_STATUSES)
            ->sum('amount');

        return Money::of((string) $total);
    }

    /**
     * @param  array<string, string>|null  $beneficiary
     *
     * @throws RefundException
     */
    protected function validateRequest(Payment $payment, Money $amount, string $mode, ?array $beneficiary, string $idempotencyKey): void
    {
        if (! Str::isUuid($idempotencyKey)) {
            throw new RefundException(trans('payment::payment_refund.messages.bad_key'), 'bad_key');
        }

        if (! $amount->isPositive()) {
            throw new RefundException(trans('payment::payment_refund.messages.bad_amount'), 'bad_amount');
        }

        if ($payment->isPrepaid() && $mode !== PaymentRefund::MODE_SOURCE) {
            throw new RefundException(trans('payment::payment_refund.messages.prepaid_to_source'), 'bad_mode');
        }

        if ($payment->isCod()) {
            $valid = match ($mode) {
                PaymentRefund::MODE_BANK => filled($beneficiary['account_number'] ?? null) && preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', strtoupper((string) ($beneficiary['ifsc'] ?? ''))) === 1,
                PaymentRefund::MODE_UPI => preg_match('/^[\w.\-]{2,256}@[a-zA-Z][a-zA-Z0-9.\-]{1,64}$/', (string) ($beneficiary['vpa'] ?? '')) === 1,
                default => false,
            };

            if (! $valid) {
                throw new RefundException(trans('payment::payment_refund.messages.cod_beneficiary'), 'bad_beneficiary');
            }
        }
    }

    protected function findByKey(string $idempotencyKey): ?PaymentRefund
    {
        return PaymentRefund::query()->where('idempotency_key', $idempotencyKey)->first();
    }

    /**
     * @throws RefundException
     */
    protected function sameRefund(PaymentRefund $existing, Payment $payment): PaymentRefund
    {
        if ($existing->payment_id !== $payment->id) {
            throw new RefundException(trans('payment::payment_refund.messages.key_reused'), 'key_reused');
        }

        return $existing;
    }
}
