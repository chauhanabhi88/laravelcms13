<?php

declare(strict_types=1);

namespace Modules\Payment\DTO;

/**
 * Outcome of checking a checkout callback signature.
 */
final readonly class VerificationResult
{
    public function __construct(
        public bool $valid,
        public ?string $gatewayOrderId = null,
        public ?string $gatewayPaymentId = null,
        public ?string $error = null,
    ) {}

    public static function valid(string $gatewayOrderId, string $gatewayPaymentId): self
    {
        return new self(true, $gatewayOrderId, $gatewayPaymentId);
    }

    public static function invalid(string $error, ?string $gatewayOrderId = null, ?string $gatewayPaymentId = null): self
    {
        return new self(false, $gatewayOrderId, $gatewayPaymentId, $error);
    }
}
