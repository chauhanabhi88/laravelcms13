<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

/**
 * Result of booking a seller order's shipment: an AWB, or why not and whether a retry may
 * succeed (a courier error or a pending AWB, not a refusal such as "already booked").
 */
final readonly class BookingOutcome
{
    public function __construct(
        public bool $booked,
        public ?string $awb = null,
        public ?string $carrierName = null,
        public ?string $error = null,
        public bool $retryable = false,
    ) {}

    public static function booked(string $awb, ?string $carrierName): self
    {
        return new self(true, $awb, $carrierName);
    }

    public static function pending(string $error): self
    {
        return new self(false, null, null, $error, true);
    }

    public static function refused(string $error): self
    {
        return new self(false, null, null, $error, false);
    }
}
