<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

/**
 * A created pickup batch: its manifest and whether the courier pickup got scheduled.
 */
final readonly class ManifestOutcome
{
    public function __construct(
        public int $pickupBatchId,
        public string $manifestNo,
        public int $shipmentsCount,
        public ?string $manifestUrl,
        public bool $pickupScheduled,
        public ?string $message = null,
    ) {}
}
