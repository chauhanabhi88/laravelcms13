<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Services\Otp\OtpSendStatus;
use Modules\Core\Support\Api\ApiCodes;
use Modules\Customer\Models\Customer;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Exceptions\PaymentException;
use Modules\Payment\Http\Controllers\Concerns\ResolvesCustomerOrder;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;

/**
 * Payments for the mobile apps (plan M11 §6): the Razorpay order id and key for the native
 * SDK, verification of the SDK's signed result, and COD OTP verify / resend.
 */
class PaymentController extends ApiController
{
    use ResolvesCustomerOrder;

    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Checkout options for the order's open prepaid payment (created when there is none).
     */
    public function initiate(Request $request): JsonResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->currentPayment($order->payableOrderId());

        try {
            if ($payment === null) {
                $payment = $this->payments->initiate($order, PaymentService::METHOD_PREPAID);
            }

            if ($payment->isCaptured()) {
                return $this->success($payment, trans('payment::front.messages.paid'));
            }

            if (! $payment->isAwaitingPayment() || ! $payment->isWindowOpen()) {
                return $this->error(trans('payment::front.errors.not_payable'), 'not_payable', 409);
            }

            $this->payments->ensureIntent($payment, $order);
        } catch (PaymentException $e) {
            return $this->error($e->getMessage(), $e->reasonCode, 422);
        }

        return $this->success($payment, null, ['checkout' => $this->payments->checkoutOptions($payment, $order)]);
    }

    /**
     * The SDK's success {razorpay_payment_id, razorpay_order_id, razorpay_signature} or error.
     */
    public function verify(Request $request): JsonResponse
    {
        $order = $this->order($request);
        $payment = $this->payments->currentPayment($order->payableOrderId());

        if ($payment === null || ! $payment->isPrepaid()) {
            return $this->error(trans('payment::front.errors.not_payable'), 'not_payable', 409);
        }

        $payload = $request->only(['razorpay_payment_id', 'razorpay_order_id', 'razorpay_signature']);

        if (is_array($request->input('error'))) {
            $payload = ['error' => $request->input('error')];
        }

        try {
            $payment = $this->payments->confirmFromCallback($payment, $payload);
        } catch (PaymentException $e) {
            return $this->error($e->getMessage(), $e->reasonCode, $e->reasonCode === 'confirming' ? 202 : 422);
        }

        return $payment->isCaptured()
            ? $this->success($payment, trans('payment::front.messages.paid'))
            : $this->success($payment, trans('payment::front.errors.confirming'), [], 202);
    }

    public function verifyCodOtp(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'digits:'.(int) config('core.otp.length', 6)]]);
        $payment = $this->codPayment($request);

        try {
            if (! $this->payments->verifyCodOtp($payment, (string) $validated['code'])) {
                return $this->error(trans('payment::front.cod_otp.invalid'), 'invalid_code', 422);
            }
        } catch (PaymentException $e) {
            return $this->error($e->getMessage(), $e->reasonCode, 422);
        }

        return $this->success($payment->refresh(), trans('payment::front.messages.cod_confirmed'));
    }

    public function resendCodOtp(Request $request): JsonResponse
    {
        $payment = $this->codPayment($request);

        try {
            $result = $this->payments->sendCodOtp($payment, $request->ip());
        } catch (PaymentException $e) {
            return $this->error($e->getMessage(), $e->reasonCode, 422);
        }

        return match ($result->status) {
            OtpSendStatus::Sent => $this->success($payment, trans('payment::front.messages.cod_otp_sent')),
            OtpSendStatus::Failed => $this->error(trans('payment::front.cod_otp.send_failed'), 'send_failed', 503),
            default => $this->error(trans('payment::front.cod_otp.wait', ['seconds' => $result->retryAfter]), 'retry_later', 429),
        };
    }

    protected function codPayment(Request $request): Payment
    {
        $order = $this->order($request);
        $payment = $this->payments->latestPayment($order->payableOrderId());

        if ($payment === null || ! $payment->isCod()) {
            abort(404);
        }

        return $payment;
    }

    protected function order(Request $request): PayableOrder
    {
        $customer = $request->user();

        return $this->customerOrder($request, $customer instanceof Customer ? $customer : null);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function success(Payment $payment, ?string $message = null, array $extra = [], int $status = 200): JsonResponse
    {
        return $this->ok([
            'payment' => [
                'method' => $payment->method,
                'status' => $payment->status,
                'amount' => $payment->amount->toDecimal(),
                'currency' => $payment->currency,
                'expires_at' => $payment->expires_at?->toIso8601String(),
                'cod_otp_required' => $payment->awaitingCodOtp(),
            ],
        ] + $extra, message: $message, status: $status);
    }

    protected function error(string $message, string $reason, int $status): JsonResponse
    {
        return $reason === 'retry_later' || $status === 429
            ? $this->fail(ApiCodes::OTP_RATE_LIMITED, $message, ['reason' => $reason], 429)
            : $this->fail($status, $message, ['reason' => $reason]);
    }
}
