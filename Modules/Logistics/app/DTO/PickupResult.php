<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

use Illuminate\Support\Carbon;

/**
 * A pickup request accepted by the courier.
 */
final readonly class PickupResult
{
    public function __construct(
        public bool $scheduled,
        public ?Carbon $scheduledAt = null,
        public ?string $reference = null,
    ) {}
}
