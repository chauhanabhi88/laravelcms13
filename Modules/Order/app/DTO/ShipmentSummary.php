<?php

declare(strict_types=1);

namespace Modules\Order\DTO;

use Illuminate\Support\Carbon;

/**
 * The forward shipment of a seller order as the order desk shows it (filled by Logistics).
 */
final readonly class ShipmentSummary
{
    public function __construct(
        public int $sellerOrderId,
        public int $shipmentId,
        public string $status,
        public string $statusLabel,
        public ?string $awb,
        public ?int $courierId,
        public ?string $carrierName,
        public ?string $trackingUrl,
        public ?string $labelUrl,
        public ?int $pickupBatchId,
        public ?string $manifestNo,
        public bool $needsAttention,
        public ?string $attentionReason,
        public ?Carbon $pickupScheduledAt,
        public ?Carbon $edd,
    ) {}

    public function isBooked(): bool
    {
        return $this->awb !== null && $this->awb !== '';
    }
}
