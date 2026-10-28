<?php

declare(strict_types=1);

namespace Modules\Payment\Drivers;

use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\DTO\PaymentIntent;
use Modules\Payment\DTO\RefundResult;
use Modules\Payment\DTO\VerificationResult;
use Modules\Payment\Exceptions\GatewayException;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentRefund;

/**
 * Cash on Delivery: nothing to register with a provider. The courier collects the cash
 * (M13) and refunds are paid out to a bank account or UPI id (RefundService).
 */
class CodGateway implements PaymentGateway
{
    public function name(): string
    {
        return Payment::METHOD_COD;
    }

    public function createIntent(Payment $payment, PayableOrder $order): PaymentIntent
    {
        return new PaymentIntent(null, $payment->amountInPaise(), $payment->currency);
    }

    public function checkoutOptions(Payment $payment, PayableOrder $order): array
    {
        return [];
    }

    public function verifyClientCallback(array $payload): VerificationResult
    {
        return VerificationResult::invalid('cod_has_no_callback');
    }

    public function fetch(string $gatewayPaymentId): GatewayPayment
    {
        throw new GatewayException('Cash on Delivery payments have no gateway record.');
    }

    public function fetchOrderPayments(string $gatewayOrderId): array
    {
        return [];
    }

    public function refund(PaymentRefund $refund): RefundResult
    {
        throw new GatewayException('Cash on Delivery refunds are paid out, not refunded to source.');
    }
}
