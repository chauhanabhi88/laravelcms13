<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Modules\Payment\Contracts\PaymentGateway;
use Modules\Payment\Drivers\CodGateway;
use Modules\Payment\Drivers\FakeGateway;
use Modules\Payment\Drivers\RazorpayGateway;
use Modules\Payment\Models\Payment;

/**
 * Resolves the gateway driver for a payment method.
 */
class PaymentGatewayManager
{
    public function __construct(private readonly Container $container) {}

    public function driver(string $method): PaymentGateway
    {
        return match ($method) {
            Payment::METHOD_RAZORPAY => $this->container->make(RazorpayGateway::class),
            Payment::METHOD_FAKE => $this->container->make(FakeGateway::class),
            Payment::METHOD_COD => $this->container->make(CodGateway::class),
            default => throw new InvalidArgumentException("Unknown payment method [{$method}]."),
        };
    }

    public function for(Payment $payment): PaymentGateway
    {
        return $this->driver($payment->method);
    }
}
