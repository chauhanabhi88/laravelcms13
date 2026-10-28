<?php

declare(strict_types=1);

namespace Modules\Payment\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\Otp\OtpSendResult;
use Modules\Core\Services\Otp\OtpService;
use Modules\Customer\Models\Customer;
use Modules\Customer\Support\PhoneNumber;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\Events\CodPaymentConfirmed;
use Modules\Payment\Events\PaymentCaptured;
use Modules\Payment\Events\PaymentFailed;
use Modules\Payment\Events\PaymentSwitchedToCod;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentAttempt;
use Modules\Payment\Support\PaymentGatewayManager;
use Modules\Payment\Support\PaymentSettings;
use Throwable;

/**
 * The payment lifecycle (plan M11 §5.1, §5.3). Every state change goes through here, inside
 * a transaction that locks the payment row, so the checkout callback, webhooks and the
 * expiry sweep can race safely: markCaptured() is idempotent and fires PaymentCaptured once.
 */
class PaymentService
{
    /**
     * Checkout's "pay online": resolved to the configured prepaid gateway (razorpay | fake).
     */
    public const METHOD_PREPAID = 'prepaid';

    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly CodEligibilityService $cod,
        private readonly OtpService $otp,
    ) {}

    public function resolveMethod(string $method): string
    {
        return $method === self::METHOD_PREPAID ? PaymentSettings::defaultGateway() : $method;
    }

    /**
     * Start paying an order (called by M12 when the order is placed, and by "Pay with COD
     * instead"). Idempotent per order and method: an open payment of the same method is
     * returned. An open payment of another method is cancelled; a paid order is refused.
     *
     * Prepaid: payment "created" with a Razorpay order and a retry window.
     * COD: payment "pending"; when an OTP is required it is sent and the window starts.
     *
     * @throws PaymentException
     */
    public function initiate(PayableOrder $order, string $method): Payment
    {
        $method = $this->resolveMethod($method);
        $this->gateways->driver($method);
        $otpRequired = false;

        if ($method === Payment::METHOD_COD) {
            $eligibility = $this->cod->checkOrder($order);

            if (! $eligibility->allowed) {
                throw new PaymentException((string) $eligibility->reason, 'cod_'.$eligibility->reasonCode);
            }

            $otpRequired = $eligibility->otpRequired;

            // A late capture of the prepaid attempt must win over the switch.
            $this->openPayments($order)
                ->filter(fn (Payment $open): bool => $open->isAwaitingPayment() && $open->gateway_order_id !== null)
                ->each(fn (Payment $open) => $this->refreshFromGateway($open));
        }

        /** @var array{0: Payment, 1: Collection<int, Payment>} $result */
        $result = DB::transaction(function () use ($order, $method, $otpRequired): array {
            $open = Payment::query()->forOrder($order->payableOrderId())->open()->orderByDesc('id')->lockForUpdate()->get();

            if ($open->contains(fn (Payment $payment): bool => $payment->isCaptured() || in_array($payment->status, [Payment::STATUS_COLLECTED, Payment::STATUS_REMITTED], true))) {
                throw new PaymentException(trans('payment::front.errors.already_paid'), 'already_paid');
            }

            $same = $open->firstWhere('method', $method);

            if ($same instanceof Payment) {
                return [$same, collect()];
            }

            foreach ($open as $other) {
                $other->failure_reason = 'switched_to_'.$method;
                $other->transitionTo(Payment::STATUS_CANCELLED, 'switched_to_'.$method);
            }

            $payment = Payment::query()->create($this->newPaymentAttributes($order, $method, $otpRequired));

            return [$payment, $open];
        });

        [$payment, $cancelled] = $result;

        if ($payment->isPrepaid()) {
            $this->ensureIntent($payment, $order);
        } elseif ($payment->wasRecentlyCreated && $payment->awaitingCodOtp()) {
            $this->sendCodOtp($payment);
        }

        $previous = $cancelled->first(fn (Payment $other): bool => $other->isPrepaid());

        if ($payment->isCod() && $previous instanceof Payment) {
            PaymentSwitchedToCod::dispatch($payment, $previous);
        }

        return $payment;
    }

    /**
     * The open payment of an order (latest first), if any.
     */
    public function currentPayment(int $salesOrderId): ?Payment
    {
        return Payment::query()->forOrder($salesOrderId)->open()->orderByDesc('id')->first();
    }

    /**
     * The order's latest payment whatever its state (for the failed / success pages).
     */
    public function latestPayment(int $salesOrderId): ?Payment
    {
        return Payment::query()->forOrder($salesOrderId)->orderByDesc('id')->first();
    }

    /**
     * Make sure a prepaid payment has its gateway order (it may be missing when the gateway
     * was down at place-order time).
     *
     * @throws PaymentException
     */
    public function ensureIntent(Payment $payment, PayableOrder $order): Payment
    {
        if (! $payment->isAwaitingPayment() || $payment->gateway_order_id !== null) {
            return $payment;
        }

        try {
            $intent = $this->gateways->for($payment)->createIntent($payment, $order);
        } catch (GatewayException $e) {
            report($e);

            throw new PaymentException(trans('payment::front.errors.gateway_unavailable'), 'gateway_unavailable');
        }

        $payment->forceFill(['gateway_order_id' => $intent->gatewayOrderId])->save();

        return $payment;
    }

    /**
     * Options for the provider's checkout (web page and mobile SDK).
     *
     * @return array<string, mixed>
     */
    public function checkoutOptions(Payment $payment, PayableOrder $order): array
    {
        return $this->gateways->for($payment)->checkoutOptions($payment, $order);
    }

    /**
     * Handle what the browser (or mobile SDK) posted after the provider's checkout closed:
     * either a signed success {razorpay_payment_id, razorpay_order_id, razorpay_signature} or
     * an error {error: {code, description, metadata: {payment_id}}}.
     *
     * The signature is verified, then the payment is fetched from the gateway so status and
     * amount come from the provider, not the browser. Returns the fresh payment: captured,
     * or still open when the gateway has only authorised it (the webhook finishes the job).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws PaymentException
     */
    public function confirmFromCallback(Payment $payment, array $payload, string $source = PaymentAttempt::SOURCE_CALLBACK): Payment
    {
        if ($payment->isCaptured()) {
            return $payment;
        }

        if (! $payment->isAwaitingPayment()) {
            throw new PaymentException(trans('payment::front.errors.not_payable'), 'not_payable');
        }

        if (isset($payload['error']) && is_array($payload['error'])) {
            $error = $payload['error'];
            $this->recordFailure(
                $payment,
                isset($error['metadata']['payment_id']) ? (string) $error['metadata']['payment_id'] : null,
                isset($error['code']) ? (string) $error['code'] : null,
                isset($error['description']) ? (string) $error['description'] : trans('payment::front.errors.cancelled_by_buyer'),
                $error,
                $source,
            );

            throw new PaymentException(trans('payment::front.errors.payment_failed'), 'payment_failed');
        }

        $gateway = $this->gateways->for($payment);
        $verification = $gateway->verifyClientCallback($payload);

        if (! $verification->valid || $verification->gatewayOrderId !== $payment->gateway_order_id) {
            $this->attempt($payment, $verification->gatewayPaymentId, PaymentAttempt::STATUS_INVALID_SIGNATURE, $source, 'invalid_signature', $verification->error ?? 'order_mismatch');
            Log::channel('integrations')->warning('payment callback rejected', ['payment_id' => $payment->id, 'error' => $verification->error ?? 'order_mismatch']);

            throw new PaymentException(trans('payment::front.errors.invalid_signature'), 'invalid_signature');
        }

        try {
            $gatewayPayment = $gateway->fetch((string) $verification->gatewayPaymentId);
        } catch (GatewayException $e) {
            report($e);

            throw new PaymentException(trans('payment::front.errors.confirming'), 'confirming');
        }

        if ($gatewayPayment->isCaptured()) {
            if (! $this->markCaptured($payment, $gatewayPayment, $source) && ! $payment->fresh()?->isCaptured()) {
                throw new PaymentException(trans('payment::front.errors.amount_mismatch'), 'amount_mismatch');
            }

            return $payment->refresh();
        }

        if ($gatewayPayment->isFailed()) {
            $this->recordFailure($payment, $gatewayPayment->id, $gatewayPayment->errorCode, $gatewayPayment->errorDescription, $gatewayPayment->raw, $source);

            throw new PaymentException(trans('payment::front.errors.payment_failed'), 'payment_failed');
        }

        // Authorised but not yet captured: auto-capture finishes it and the webhook reports it.
        $this->attempt($payment, $gatewayPayment->id, $gatewayPayment->status, $source, null, null, $gatewayPayment->raw);
        $this->moveToAttempted($payment);

        return $payment->refresh();
    }

    /**
     * Record a capture. Idempotent: returns true only the first time (and only then fires
     * PaymentCaptured). A capture for the wrong order or amount is logged as an attempt and
     * refused.
     */
    public function markCaptured(Payment $payment, GatewayPayment $gatewayPayment, string $source): bool
    {
        $captured = DB::transaction(function () use ($payment, $gatewayPayment, $source): bool {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($gatewayPayment->orderId !== null && $locked->gateway_order_id !== null && $gatewayPayment->orderId !== $locked->gateway_order_id) {
                $this->attempt($locked, $gatewayPayment->id, PaymentAttempt::STATUS_MISMATCH, $source, 'order_mismatch', 'Gateway order '.$gatewayPayment->orderId, $gatewayPayment->raw);

                return false;
            }

            if ($gatewayPayment->amountPaise !== $locked->amountInPaise() || strtoupper($gatewayPayment->currency) !== strtoupper($locked->currency)) {
                $this->attempt($locked, $gatewayPayment->id, PaymentAttempt::STATUS_MISMATCH, $source, 'amount_mismatch', sprintf('Gateway %d %s, expected %d %s', $gatewayPayment->amountPaise, $gatewayPayment->currency, $locked->amountInPaise(), $locked->currency), $gatewayPayment->raw);
                Log::channel('integrations')->warning('payment capture amount mismatch', ['payment_id' => $locked->id, 'gateway_payment_id' => $gatewayPayment->id]);

                return false;
            }

            if ($locked->isCaptured()) {
                if ($locked->gateway_payment_id !== $gatewayPayment->id) {
                    // A second successful payment on the same order: Finance must refund it.
                    $this->attempt($locked, $gatewayPayment->id, 'duplicate_capture', $source, 'duplicate_capture', 'The order was already paid by '.$locked->gateway_payment_id, $gatewayPayment->raw);
                    Log::channel('integrations')->warning('duplicate capture on a paid order', ['payment_id' => $locked->id, 'gateway_payment_id' => $gatewayPayment->id]);
                }

                return false;
            }

            if (! $locked->canTransitionTo(Payment::STATUS_CAPTURED)) {
                // e.g. the buyer switched to COD, then the bank captured anyway: needs a refund.
                $this->attempt($locked, $gatewayPayment->id, 'captured_after_close', $source, 'captured_after_close', 'Payment was '.$locked->status.' when the capture arrived', $gatewayPayment->raw);
                Log::channel('integrations')->warning('capture arrived for a closed payment', ['payment_id' => $locked->id, 'status' => $locked->status]);

                return false;
            }

            $locked->fill([
                'gateway_payment_id' => $gatewayPayment->id,
                'instrument' => $gatewayPayment->instrument,
                'instrument_meta' => $gatewayPayment->instrumentMeta === [] ? null : $gatewayPayment->instrumentMeta,
                'captured_at' => Carbon::now(),
                'failure_reason' => null,
            ]);
            $locked->transitionTo(Payment::STATUS_CAPTURED, null, ['source' => $source, 'gateway_payment_id' => $gatewayPayment->id]);
            $this->attempt($locked, $gatewayPayment->id, PaymentAttempt::STATUS_CAPTURED, $source, null, null, $gatewayPayment->raw);

            PaymentCaptured::dispatch($locked);

            return true;
        });

        $payment->refresh();

        return $captured;
    }

    /**
     * A failed or dismissed attempt: logged, and the payment stays open for a retry until
     * its window closes. Never changes a captured payment.
     *
     * @param  array<string, mixed>  $raw
     */
    public function recordFailure(Payment $payment, ?string $gatewayPaymentId, ?string $errorCode, ?string $errorDescription, array $raw, string $source): void
    {
        DB::transaction(function () use ($payment, $gatewayPaymentId, $errorCode, $errorDescription, $raw, $source): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $this->attempt($locked, $gatewayPaymentId, PaymentAttempt::STATUS_FAILED, $source, $errorCode, $errorDescription, $raw);

            if ($locked->isAwaitingPayment()) {
                $locked->failure_reason = $errorDescription === null ? null : mb_substr($errorDescription, 0, 255);
                $this->moveToAttempted($locked);
            }
        });

        $payment->refresh();
    }

    /**
     * Close a prepaid payment whose window ended without a capture and fire PaymentFailed.
     */
    public function markFailed(Payment $payment, string $reason): bool
    {
        $failed = DB::transaction(function () use ($payment, $reason): bool {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->isAwaitingPayment()) {
                return false;
            }

            $locked->failure_reason = $reason;
            $locked->transitionTo(Payment::STATUS_FAILED, $reason);
            $this->attempt($locked, null, PaymentAttempt::STATUS_FAILED, PaymentAttempt::SOURCE_EXPIRY, $reason, null);

            PaymentFailed::dispatch($locked, $reason);

            return true;
        });

        $payment->refresh();

        return $failed;
    }

    /**
     * Ask the gateway whether any attempt on the payment's order was captured, and record it.
     *
     * @throws GatewayException
     */
    public function refreshFromGateway(Payment $payment): bool
    {
        if ($payment->gateway_order_id === null || ! $payment->isAwaitingPayment()) {
            return false;
        }

        foreach ($this->gateways->for($payment)->fetchOrderPayments($payment->gateway_order_id) as $gatewayPayment) {
            if ($gatewayPayment->isCaptured()) {
                return $this->markCaptured($payment, $gatewayPayment, PaymentAttempt::SOURCE_EXPIRY);
            }
        }

        return false;
    }

    /**
     * The 5-minute sweep (ExpireUnpaidPayments): prepaid payments past their window get a
     * last gateway check (late capture → captured), else fail; COD payments whose OTP was not
     * verified in time are cancelled. Both failures fire PaymentFailed.
     *
     * @return array{captured: int, failed: int, cod_cancelled: int, skipped: int}
     */
    public function expireStale(?Carbon $now = null, int $limit = 200): array
    {
        $now ??= Carbon::now();
        $result = ['captured' => 0, 'failed' => 0, 'cod_cancelled' => 0, 'skipped' => 0];

        $prepaid = Payment::query()
            ->whereIn('status', Payment::OPEN_PREPAID_STATUSES)
            ->where('method', '!=', Payment::METHOD_COD)
            ->where('expires_at', '<', $now)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($prepaid as $payment) {
            try {
                if ($this->refreshFromGateway($payment)) {
                    $result['captured']++;

                    continue;
                }
            } catch (Throwable $e) {
                // Gateway unreachable: try again on the next run rather than fail a paid order.
                report($e);
                $result['skipped']++;

                continue;
            }

            if ($this->markFailed($payment, PaymentFailed::REASON_WINDOW_EXPIRED)) {
                $result['failed']++;
            }
        }

        $unverifiedCod = Payment::query()
            ->where('method', Payment::METHOD_COD)
            ->where('status', Payment::STATUS_PENDING)
            ->where('cod_otp_required', config('core.yes'))
            ->whereNull('cod_otp_verified_at')
            ->where('expires_at', '<', $now)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($unverifiedCod as $payment) {
            if ($this->cancelUnverifiedCod($payment)) {
                $result['cod_cancelled']++;
            }
        }

        return $result;
    }

    /**
     * Send (or resend) the COD confirmation OTP to the buyer's phone, else their email.
     *
     * @throws PaymentException
     */
    public function sendCodOtp(Payment $payment, ?string $ip = null): OtpSendResult
    {
        if (! $payment->awaitingCodOtp() || ! $payment->isWindowOpen()) {
            throw new PaymentException(trans('payment::front.errors.cod_otp_not_needed'), 'cod_otp_not_needed');
        }

        [$channel, $identifier] = $this->codOtpDestination($payment);

        return $this->otp->send($channel, $identifier, $this->codOtpPurpose($payment), $ip);
    }

    /**
     * Verify the COD OTP; on success the payment is confirmed and CodPaymentConfirmed fires.
     *
     * @throws PaymentException
     */
    public function verifyCodOtp(Payment $payment, string $code): bool
    {
        if (! $payment->awaitingCodOtp()) {
            if ($payment->requiresCodOtp() && $payment->cod_otp_verified_at !== null) {
                return true;
            }

            throw new PaymentException(trans('payment::front.errors.cod_otp_not_needed'), 'cod_otp_not_needed');
        }

        if (! $payment->isWindowOpen()) {
            throw new PaymentException(trans('payment::front.errors.cod_otp_expired'), 'cod_otp_expired');
        }

        [, $identifier] = $this->codOtpDestination($payment);

        if (! $this->otp->verify($identifier, $this->codOtpPurpose($payment), $code)) {
            return false;
        }

        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->cod_otp_verified_at !== null) {
                return;
            }

            $locked->forceFill(['cod_otp_verified_at' => Carbon::now()])->save();
            CodPaymentConfirmed::dispatch($locked);
        });

        $payment->refresh();

        return true;
    }

    /**
     * Masked phone or email the COD OTP goes to.
     */
    public function codOtpMaskedDestination(Payment $payment): string
    {
        [$channel, $identifier] = $this->codOtpDestination($payment);

        return $channel === OtpService::CHANNEL_SMS ? PhoneNumber::mask($identifier) : PhoneNumber::maskEmail($identifier);
    }

    /**
     * The courier collected the cash (Logistics, M13).
     */
    public function markCodCollected(Payment $payment, ?Model $actor = null): Payment
    {
        return $this->transitionLocked($payment, Payment::STATUS_COLLECTED, $actor);
    }

    /**
     * The courier's COD remittance was reconciled (Finance, M17).
     */
    public function markCodRemitted(Payment $payment, ?Model $actor = null): Payment
    {
        return $this->transitionLocked($payment, Payment::STATUS_REMITTED, $actor);
    }

    protected function cancelUnverifiedCod(Payment $payment): bool
    {
        $cancelled = DB::transaction(function () use ($payment): bool {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->awaitingCodOtp()) {
                return false;
            }

            $locked->failure_reason = PaymentFailed::REASON_COD_OTP_EXPIRED;
            $locked->transitionTo(Payment::STATUS_CANCELLED, PaymentFailed::REASON_COD_OTP_EXPIRED);

            PaymentFailed::dispatch($locked, PaymentFailed::REASON_COD_OTP_EXPIRED);

            return true;
        });

        $payment->refresh();

        return $cancelled;
    }

    /**
     * @return Collection<int, Payment>
     */
    protected function openPayments(PayableOrder $order): Collection
    {
        return Payment::query()->forOrder($order->payableOrderId())->open()->orderByDesc('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function newPaymentAttributes(PayableOrder $order, string $method, bool $otpRequired): array
    {
        $now = Carbon::now();
        $isCod = $method === Payment::METHOD_COD;

        return [
            'sales_order_id' => $order->payableOrderId(),
            'order_no' => $order->payableOrderNo(),
            'customer_id' => $order->payableCustomer()->id,
            'method' => $method,
            'status' => $isCod ? Payment::STATUS_PENDING : Payment::STATUS_CREATED,
            'amount' => $order->payableAmount($method),
            'amount_refunded' => '0',
            'currency' => 'INR',
            'expires_at' => match (true) {
                ! $isCod => $now->copy()->addMinutes(PaymentSettings::paymentWindowMinutes()),
                $otpRequired => $now->copy()->addMinutes(PaymentSettings::codOtpWindowMinutes()),
                default => null,
            },
            'cod_otp_required' => $isCod && $otpRequired ? config('core.yes') : config('core.no'),
        ];
    }

    /**
     * @return array{0: string, 1: string} channel and identifier
     *
     * @throws PaymentException
     */
    protected function codOtpDestination(Payment $payment): array
    {
        $customer = $payment->relationLoaded('customer') ? $payment->customer : Customer::query()->find($payment->customer_id);

        if ($customer instanceof Customer && filled($customer->phone)) {
            return [OtpService::CHANNEL_SMS, (string) $customer->phone];
        }

        if ($customer instanceof Customer && filled($customer->email)) {
            return [OtpService::CHANNEL_EMAIL, (string) $customer->email];
        }

        throw new PaymentException(trans('payment::front.errors.cod_otp_no_contact'), 'cod_otp_no_contact');
    }

    /**
     * One OTP purpose per payment, so two COD orders never share a code.
     */
    protected function codOtpPurpose(Payment $payment): string
    {
        return config('payment.cod_otp_purpose', 'cod_confirm').':'.$payment->id;
    }

    protected function moveToAttempted(Payment $payment): void
    {
        if ($payment->status === Payment::STATUS_CREATED) {
            $payment->transitionTo(Payment::STATUS_ATTEMPTED);
        } elseif ($payment->isDirty()) {
            $payment->save();
        }
    }

    /**
     * @param  array<string, mixed>|null  $raw
     */
    protected function attempt(Payment $payment, ?string $gatewayPaymentId, string $status, string $source, ?string $errorCode, ?string $errorDescription, ?array $raw = null): PaymentAttempt
    {
        return PaymentAttempt::query()->create([
            'payment_id' => $payment->id,
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => mb_substr($status, 0, 20),
            'source' => $source,
            'error_code' => $errorCode === null ? null : mb_substr($errorCode, 0, 60),
            'error_description' => $errorDescription === null ? null : mb_substr($errorDescription, 0, 255),
            'raw' => $raw === [] ? null : $raw,
        ]);
    }

    /**
     * @throws PaymentException
     */
    protected function transitionLocked(Payment $payment, string $to, ?Model $actor): Payment
    {
        DB::transaction(function () use ($payment, $to, $actor): void {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->canTransitionTo($to)) {
                throw new PaymentException(trans('payment::payment.messages.invalid_transition', ['from' => $locked->status, 'to' => $to]), 'invalid_transition');
            }

            $locked->transitionTo($to, null, [], $actor);
        });

        return $payment->refresh();
    }
}
