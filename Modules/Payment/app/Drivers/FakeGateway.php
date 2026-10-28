<?php

declare(strict_types=1);

namespace Modules\Payment\Drivers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\DTO\PaymentIntent;
use Modules\Payment\DTO\RefundResult;
use Modules\Payment\DTO\VerificationResult;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Support\RazorpaySignature;

/**
 * Offline stand-in for Razorpay (local work and tests). It behaves like the real driver:
 * orders and payments live in the cache, callbacks are HMAC-signed with
 * config('payment.fake.key_secret'), and simulate() plays the buyer paying.
 */
class FakeGateway implements PaymentGateway
{
    private const TTL_SECONDS = 86400;

    /**
     * Status the next refund() call returns (tests flip it to "processed" or "failed").
     */
    public string $refundOutcome = PaymentRefund::STATUS_PROCESSING;

    public function name(): string
    {
        return Payment::METHOD_FAKE;
    }

    public function createIntent(Payment $payment, PayableOrder $order): PaymentIntent
    {
        $orderId = 'order_fake_'.Str::random(14);

        Cache::put($this->orderKey($orderId), ['amount' => $payment->amountInPaise(), 'payments' => []], self::TTL_SECONDS);

        return new PaymentIntent($orderId, $payment->amountInPaise(), $payment->currency, ['id' => $orderId, 'receipt' => $order->payableOrderNo()]);
    }

    public function checkoutOptions(Payment $payment, PayableOrder $order): array
    {
        return [
            'key' => (string) config('payment.fake.key_id'),
            'order_id' => $payment->gateway_order_id,
            'amount' => $payment->amountInPaise(),
            'currency' => $payment->currency,
            'name' => (string) config('app.name'),
            'fake' => true,
        ];
    }

    public function verifyClientCallback(array $payload): VerificationResult
    {
        $orderId = (string) ($payload['razorpay_order_id'] ?? '');
        $paymentId = (string) ($payload['razorpay_payment_id'] ?? '');

        if (! RazorpaySignature::verifyCallback($orderId, $paymentId, (string) ($payload['razorpay_signature'] ?? ''), $this->secret())) {
            return VerificationResult::invalid('invalid_signature', $orderId ?: null, $paymentId ?: null);
        }

        return VerificationResult::valid($orderId, $paymentId);
    }

    public function fetch(string $gatewayPaymentId): GatewayPayment
    {
        $payment = Cache::get($this->paymentKey($gatewayPaymentId));

        if (! is_array($payment)) {
            throw new GatewayException("Fake payment [{$gatewayPaymentId}] does not exist.");
        }

        return GatewayPayment::fromRazorpay($payment);
    }

    public function fetchOrderPayments(string $gatewayOrderId): array
    {
        $order = (array) Cache::get($this->orderKey($gatewayOrderId), []);

        return array_values(array_filter(array_map(
            fn (string $id): ?GatewayPayment => is_array($entity = Cache::get($this->paymentKey($id))) ? GatewayPayment::fromRazorpay($entity) : null,
            (array) ($order['payments'] ?? []),
        )));
    }

    public function refund(PaymentRefund $refund): RefundResult
    {
        if ($this->refundOutcome === PaymentRefund::STATUS_FAILED) {
            return RefundResult::failed('Fake refund failure.');
        }

        return RefundResult::fromRazorpay([
            'id' => 'rfnd_fake_'.Str::random(14),
            'amount' => $refund->amount->paise,
            'status' => $this->refundOutcome === PaymentRefund::STATUS_PROCESSED ? 'processed' : 'pending',
        ]);
    }

    /**
     * Play the buyer: record a payment against a fake order and return the signed callback
     * payload the browser would post.
     *
     * @return array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: string}
     */
    public function simulate(string $gatewayOrderId, string $status = GatewayPayment::STATUS_CAPTURED, ?int $amountPaise = null, string $method = 'upi'): array
    {
        $order = (array) Cache::get($this->orderKey($gatewayOrderId), ['amount' => $amountPaise ?? 0, 'payments' => []]);
        $paymentId = 'pay_fake_'.Str::random(14);

        Cache::put($this->paymentKey($paymentId), array_filter([
            'id' => $paymentId,
            'order_id' => $gatewayOrderId,
            'status' => $status,
            'amount' => $amountPaise ?? (int) ($order['amount'] ?? 0),
            'currency' => 'INR',
            'method' => $method,
            'vpa' => $method === 'upi' ? 'buyer@upi' : null,
            'error_code' => $status === GatewayPayment::STATUS_FAILED ? 'BAD_REQUEST_ERROR' : null,
            'error_description' => $status === GatewayPayment::STATUS_FAILED ? 'Payment was declined by the bank.' : null,
        ], fn (mixed $value): bool => $value !== null), self::TTL_SECONDS);

        $order['payments'] = array_merge((array) ($order['payments'] ?? []), [$paymentId]);
        Cache::put($this->orderKey($gatewayOrderId), $order, self::TTL_SECONDS);

        return [
            'razorpay_order_id' => $gatewayOrderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => RazorpaySignature::forCallback($gatewayOrderId, $paymentId, $this->secret()),
        ];
    }

    private function secret(): string
    {
        return (string) config('payment.fake.key_secret');
    }

    private function orderKey(string $orderId): string
    {
        return 'payment.fake.order.'.$orderId;
    }

    private function paymentKey(string $paymentId): string
    {
        return 'payment.fake.payment.'.$paymentId;
    }
}
