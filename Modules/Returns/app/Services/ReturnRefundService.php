<?php

declare(strict_types=1);

namespace Modules\Returns\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Exceptions\RefundException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Services\RefundService;
use Modules\Returns\DTO\RefundQuote;
use Modules\Returns\Events\ReturnRefunded;
use Modules\Returns\Exceptions\ReturnException;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Support\ReturnSettings;
use Ramsey\Uuid\Uuid;

/**
 * Return refunds (plan M15 §5.5).
 *
 * quote(): Σ unit price × qty minus the item's coupon share for the returned quantity (a
 * reseller's COD order refunds what their customer paid), plus the package's delivery share
 * when the seller is at fault and no earlier return of the package refunded it.
 *
 * initiate(): one RefundService::refund() per return and stage (idempotency key derived from
 * the return number): prepaid → back to source, COD → the bank account / UPI id captured in
 * the return flow. RefundProcessed / RefundFailed come back through markRefunded() / markFailed().
 */
class ReturnRefundService
{
    public const STAGE_AUTO = 'auto';

    public const STAGE_FORCE = 'force';

    public function __construct(private readonly RefundService $refunds) {}

    /**
     * @param  array<int, int>  $quantities  sales_order_item_id => quantity
     */
    public function quote(SellerOrder $sellerOrder, array $quantities, string $reasonCode, ?int $exceptReturnId = null): RefundQuote
    {
        $sellerOrder->loadMissing(['salesOrder', 'items']);
        $order = $sellerOrder->salesOrder;
        $units = [];
        $lines = [];

        foreach ($sellerOrder->items as $item) {
            $qty = (int) ($quantities[$item->id] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            [$units[$item->id], $lines[$item->id]] = $this->itemRefund($order, $item, $qty);
        }

        $delivery = Money::zero();

        if (ReturnRequest::sellerFaultReason($reasonCode) && ReturnSettings::refundDeliveryFeeOnSellerFault() && $sellerOrder->delivery_charge_share->isPositive()) {
            $alreadyRefunded = ReturnRequest::query()
                ->where('seller_order_id', $sellerOrder->id)
                ->whereNotIn('status', ReturnRequest::WITHDRAWN_STATUSES)
                ->where('delivery_refund', '>', 0)
                ->when($exceptReturnId !== null, fn ($query) => $query->whereKeyNot($exceptReturnId))
                ->exists();

            $delivery = $alreadyRefunded ? Money::zero() : $sellerOrder->delivery_charge_share;
        }

        return new RefundQuote($units, $lines, $delivery);
    }

    /**
     * [unit, line] refund of $qty units of the item.
     *
     * @return array{0: Money, 1: Money}
     */
    public function itemRefund(SalesOrder $order, SalesOrderItem $item, int $qty): array
    {
        $unit = $order->isResellerOrder() && $order->isCod() && $item->customer_price !== null ? $item->customer_price : $item->price;
        $coupon = Money::zero();

        if ($item->discount_allocated->isPositive() && $item->quantity > 0) {
            $coupon = $qty >= $item->quantity ? $item->discount_allocated : $item->discount_allocated->allocate([$qty, $item->quantity - $qty])[0];
        }

        $line = $unit->times($qty)->minus($coupon);
        $line = $line->isNegative() ? Money::zero() : $line;

        return [$qty > 0 ? $line->allocate(array_fill(0, $qty, 1))[0] : Money::zero(), $line];
    }

    /**
     * The order's payment that money can go back to (captured prepaid, or collected COD).
     */
    public function refundablePayment(SalesOrder|int $order): ?Payment
    {
        return Payment::query()
            ->forOrder($order instanceof SalesOrder ? $order->id : $order)
            ->whereIn('status', Payment::REFUNDABLE_STATUSES)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Start the refund of a return (idempotent per stage). $amount defaults to the return's
     * refund_amount (an exchange falling back to a refund: its items' value); it is capped at
     * what the payment still holds. A COD refund may take the bank / UPI details from
     * $beneficiary when the return has none (admin console).
     *
     * @param  array{mode: string, beneficiary: array<string, string>}|null  $override
     *
     * @throws ReturnException
     */
    public function initiate(ReturnRequest $return, ?Money $amount = null, string $stage = self::STAGE_AUTO, ?array $override = null): PaymentRefund
    {
        $return->loadMissing('items');
        $payment = $this->refundablePayment($return->sales_order_id);

        if ($payment === null) {
            throw new ReturnException(trans('returns::front.errors.no_refundable_payment'), 'no_payment');
        }

        $key = Uuid::uuid5(Uuid::NAMESPACE_URL, 'return-refund:'.$return->return_no.':'.$stage)->toString();

        // Idempotent: the same stage returns its refund (its amount no longer counts as available).
        if (($existing = PaymentRefund::query()->where('idempotency_key', $key)->first()) !== null) {
            return $existing;
        }

        $amount ??= $return->isExchange()
            ? $return->items->reduce(fn (Money $sum, $item): Money => $sum->plus($item->line_refund), Money::zero())
            : $return->refund_amount;
        $available = $this->refunds->refundableAmount($payment);
        $amount = $amount->compare($available) > 0 ? $available : $amount;

        if (! $amount->isPositive()) {
            throw new ReturnException(trans('returns::front.errors.nothing_to_refund'), 'nothing_to_refund');
        }

        [$mode, $beneficiary] = $this->destination($return, $payment, $override);

        try {
            $refund = $this->refunds->refund($payment, $amount, $mode, $beneficiary, trans('returns::returns.refund_reason', ['return_no' => $return->return_no]), $key, $return->id);
        } catch (RefundException $e) {
            throw new ReturnException($e->getMessage(), $e->reasonCode);
        }

        if ($refund->isFinal()) {
            $this->markRefunded($refund);
        } else {
            ReturnRequest::query()
                ->whereKey($return->id)
                ->where(fn ($query) => $query->whereNull('refund_status')->orWhere('refund_status', '!=', ReturnRequest::REFUND_REFUNDED))
                ->update(['refund_status' => ReturnRequest::REFUND_INITIATED, 'updated_at' => Carbon::now()]);
        }

        $return->refresh();

        return $refund;
    }

    /**
     * RefundProcessed for a return's refund: money reached the customer. A return waiting for
     * it (refund_initiated) becomes refunded; ReturnRefunded fires for M17.
     */
    public function markRefunded(PaymentRefund $refund): ?ReturnRequest
    {
        if ($refund->return_request_id === null) {
            return null;
        }

        $return = DB::transaction(function () use ($refund): ?ReturnRequest {
            $locked = ReturnRequest::query()->lockForUpdate()->find($refund->return_request_id);

            if ($locked === null) {
                return null;
            }

            $wasRefunded = $locked->refund_status === ReturnRequest::REFUND_REFUNDED;
            $locked->forceFill([
                'refund_status' => ReturnRequest::REFUND_REFUNDED,
                'refunded_at' => $locked->refunded_at ?? Carbon::now(),
                'needs_attention' => (int) config('core.no'),
                'attention_reason' => null,
            ])->save();

            if ($locked->status === ReturnRequest::STATUS_REFUND_INITIATED) {
                $locked->transitionTo(ReturnRequest::STATUS_REFUNDED, null, ['refund_id' => $refund->id]);
            }

            if (! $wasRefunded) {
                ReturnRefunded::dispatch($locked, $refund);
            }

            return $locked;
        });

        return $return;
    }

    /**
     * RefundFailed for a return's refund: CX retries from the refunds screen or forces a new one.
     */
    public function markFailed(PaymentRefund $refund): void
    {
        if ($refund->return_request_id === null) {
            return;
        }

        ReturnRequest::query()
            ->whereKey($refund->return_request_id)
            ->where(fn ($query) => $query->whereNull('refund_status')->orWhere('refund_status', '!=', ReturnRequest::REFUND_REFUNDED))
            ->update([
                'refund_status' => ReturnRequest::REFUND_FAILED,
                'needs_attention' => (int) config('core.yes'),
                'attention_reason' => mb_substr(trans('returns::returns.attention.refund_failed', ['reason' => (string) $refund->failure_reason]), 0, 255),
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * [mode, beneficiary in RefundService's shape] for the payment.
     *
     * @param  array{mode: string, beneficiary: array<string, string>}|null  $override
     * @return array{0: string, 1: array<string, string>|null}
     *
     * @throws ReturnException
     */
    protected function destination(ReturnRequest $return, Payment $payment, ?array $override): array
    {
        if ($payment->isPrepaid()) {
            return [PaymentRefund::MODE_SOURCE, null];
        }

        $mode = $override['mode'] ?? $return->refund_mode;
        $details = $override['beneficiary'] ?? (array) ($return->refund_beneficiary ?? []);

        $beneficiary = match ($mode) {
            PaymentRefund::MODE_BANK => isset($details['account_number'], $details['ifsc']) ? [
                'account_number' => (string) $details['account_number'],
                'ifsc' => strtoupper((string) $details['ifsc']),
                'name' => (string) ($details['account_holder'] ?? $details['name'] ?? ''),
            ] : null,
            PaymentRefund::MODE_UPI => isset($details['vpa']) ? ['vpa' => (string) $details['vpa']] : null,
            default => null,
        };

        if ($beneficiary === null) {
            throw new ReturnException(trans('returns::front.errors.refund_details_missing'), 'refund_details_missing');
        }

        return [(string) $mode, $beneficiary];
    }
}
