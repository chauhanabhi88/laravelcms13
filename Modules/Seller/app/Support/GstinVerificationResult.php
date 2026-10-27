<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

/**
 * Answer of a GstinVerifier. checked = false means no lookup happened (null driver or
 * the API was unavailable); the seller's gst_status then stays "unverified".
 */
final readonly class GstinVerificationResult
{
    public function __construct(
        public bool $checked,
        public ?string $legalName = null,
        public bool $active = false,
        public ?string $message = null,
    ) {}

    public static function unchecked(?string $message = null): self
    {
        return new self(false, message: $message);
    }
}
