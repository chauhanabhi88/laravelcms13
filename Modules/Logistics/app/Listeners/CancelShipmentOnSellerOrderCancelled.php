<?php

declare(strict_types=1);

namespace Modules\Logistics\Listeners;

use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Services\ShipmentService;
use Modules\Logistics\Support\ShipmentAlerts;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * A cancelled seller order (customer, seller, admin or system, before pickup) cancels its
 * forward shipment, with the courier when it was booked. A courier refusal flags the shipment
 * for Logistics Ops instead of failing the cancellation.
 */
class CancelShipmentOnSellerOrderCancelled
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly ShipmentAlerts $alerts,
    ) {}

    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_CANCELLED) {
            return;
        }

        $open = Shipment::query()
            ->forward()
            ->where('seller_order_id', $event->sellerOrder->id)
            ->whereIn('status', Shipment::PRE_PICKUP_STATUSES)
            ->get();

        foreach ($open as $shipment) {
            try {
                $this->shipments->cancel($shipment, trans('logistics::shipment.messages.order_cancelled'));
            } catch (Throwable $e) {
                report($e);
                $this->alerts->flag($shipment, trans('logistics::shipment.attention.cancel_failed', ['error' => $e->getMessage()]));
            }
        }
    }
}
