<?php

declare(strict_types=1);

namespace Modules\Logistics\DTO;

/**
 * The provider's ids for a created (forward or return) shipment.
 */
final readonly class ProviderShipment
{
    public function __construct(
        public ?string $orderId,
        public ?string $shipmentId,
    ) {}
}
