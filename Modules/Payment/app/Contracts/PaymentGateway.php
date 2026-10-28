<?php

declare(strict_types=1);

namespace Modules\Payment\Contracts;

use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\DTO\PaymentIntent;
use Modules\Payment\DTO\RefundResult;
use Modules\Payment\DTO\VerificationResult;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;

/**
 * A payment provider driver (FR-PAY-001): razorpay, cod or fake. Resolved by method name
 * through Support\PaymentGatewayManager. Network failures throw GatewayException.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Register the payment with the provider (a Razorpay order); a no-op for COD.
     *
     * @throws GatewayException
     */
    public function createIntent(Payment $payment, PayableOrder $order): PaymentIntent;

    /**
     * What the browser / mobile SDK needs to open the provider's checkout.
     *
     * @return array<string, mixed>
     */
    public function checkoutOptions(Payment $payment, PayableOrder $order): array;

    /**
     * Check the signature the provider's checkout returned to the browser.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyClientCallback(array $payload): VerificationResult;

    /**
     * @throws GatewayException
     */
    public function fetch(string $gatewayPaymentId): GatewayPayment;

    /**
     * Every payment made against a provider order (to catch a late capture).
     *
     * @return list<GatewayPayment>
     *
     * @throws GatewayException
     */
    public function fetchOrderPayments(string $gatewayOrderId): array;

    /**
     * Refund to source. The final state usually arrives later by webhook.
     *
     * @throws GatewayException
     */
    public function refund(PaymentRefund $refund): RefundResult;
}
