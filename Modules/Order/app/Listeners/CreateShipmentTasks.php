<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Modules\Order\Contracts\ShipmentPlanner;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Hands each confirmed seller order to the ShipmentPlanner (a no-op until Logistics, M13).
 */
class CreateShipmentTasks
{
    public function __construct(private readonly ShipmentPlanner $planner) {}

    public function handle(OrderConfirmed $event): void
    {
        foreach ($event->order->loadMissing('sellerOrders')->sellerOrders as $sellerOrder) {
            /** @var SellerOrder $sellerOrder */
            if ($sellerOrder->status !== SellerOrder::STATUS_PENDING_ACCEPTANCE) {
                continue;
            }

            try {
                $this->planner->plan($sellerOrder);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
