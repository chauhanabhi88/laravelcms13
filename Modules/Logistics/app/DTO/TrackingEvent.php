<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

use Illuminate\Support\Carbon;

/**
 * One courier scan as read from a webhook, a tracking poll or Logistics Ops. $status is our
 * mapped shipment status (null = unknown courier status, kept without a transition).
 */
final readonly class TrackingEvent
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $status,
        public string $providerStatus,
        public Carbon $occurredAt,
        public string $providerStatusCode = '',
        public ?string $location = null,
        public ?string $remarks = null,
        public array $raw = [],
    ) {}
}
