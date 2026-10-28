<?php

declare(strict_types=1);

namespace Modules\Payment\DTO;

/**
 * The provider-side record created for a payment (a Razorpay order).
 */
final readonly class PaymentIntent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $gatewayOrderId,
        public int $amountPaise,
        public string $currency = 'INR',
        public array $raw = [],
    ) {}
}
