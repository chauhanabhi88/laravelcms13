<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Logistics\Services\ShipmentService;
use Modules\Order\Contracts\ShipmentPlanner;
use Modules\Order\Models\SellerOrder;

/**
 * The Order module's ShipmentPlanner (called by its CreateShipmentTasks listener when a
 * seller order reaches pending_acceptance): creates the forward shipment row, no provider call
 * (plan M13 §5.1). Booking happens when the seller accepts (M14 → ShipmentService::book()).
 */
class LogisticsShipmentPlanner implements ShipmentPlanner
{
    public function __construct(private readonly ShipmentService $shipments) {}

    public function plan(SellerOrder $sellerOrder): void
    {
        $this->shipments->plan($sellerOrder);
    }
}
