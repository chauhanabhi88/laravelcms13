<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

use Illuminate\Support\Carbon;

/**
 * The tracking number the courier assigned, with the carrier that will move the package.
 * A null awb means none was assigned (the manual driver waits for Logistics Ops).
 */
final readonly class AwbResult
{
    public function __construct(
        public ?string $awb,
        public ?string $carrierId = null,
        public ?string $carrierName = null,
        public ?int $chargedWeightGrams = null,
        public ?string $trackingUrl = null,
        public ?Carbon $edd = null,
    ) {}

    public function assigned(): bool
    {
        return $this->awb !== null && $this->awb !== '';
    }
}
