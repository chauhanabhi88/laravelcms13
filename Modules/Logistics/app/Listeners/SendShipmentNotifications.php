<?php

declare(strict_types=1);

namespace Modules\Logistics\Listeners;

use Modules\Logistics\Events\NdrRaised;
use Modules\Logistics\Events\ShipmentStatusChanged;
use Modules\Logistics\Models\Shipment;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Customer notifications from the M09 catalogue for forward shipments: order.shipped (first
 * courier scan), order.out_for_delivery (with the cash to keep ready), order.delivered and
 * order.delivery_failed (NDR). A notification failure never breaks tracking.
 */
class SendShipmentNotifications
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handleStatusChanged(ShipmentStatusChanged $event): void
    {
        $shipment = $event->shipment;

        if ($shipment->direction !== Shipment::DIRECTION_FORWARD) {
            return;
        }

        $key = match (true) {
            in_array($event->to, [Shipment::STATUS_PICKED_UP, Shipment::STATUS_IN_TRANSIT], true)
                && Shipment::RANKS[$event->from] < Shipment::RANKS[Shipment::STATUS_PICKED_UP] => 'order.shipped',
            $event->to === Shipment::STATUS_OUT_FOR_DELIVERY => 'order.out_for_delivery',
            $event->to === Shipment::STATUS_DELIVERED => 'order.delivered',
            default => null,
        };

        if ($key === null) {
            return;
        }

        [$order, $sellerOrder] = $this->orders($shipment);

        if ($order === null || $order->customer === null) {
            return;
        }

        $data = match ($key) {
            'order.shipped' => [
                'courier' => (string) ($shipment->carrier_name ?? ''),
                'awb' => (string) ($shipment->awb ?? ''),
                'track_url' => $this->trackUrl($shipment),
            ],
            'order.out_for_delivery' => ['amount_due' => $shipment->isCod() ? $shipment->cod_amount->toDecimal() : '0.00'],
            default => [],
        };

        $this->send($key, $order, $data + ['order_no' => $sellerOrder?->sub_order_no ?? $order->order_no]);
    }

    public function handleNdr(NdrRaised $event): void
    {
        $event->ndrCase->loadMissing('shipment');
        [$order, $sellerOrder] = $this->orders($event->ndrCase->shipment);

        if ($order === null || $order->customer === null) {
            return;
        }

        $this->send('order.delivery_failed', $order, [
            'order_no' => $sellerOrder?->sub_order_no ?? $order->order_no,
            'reason' => (string) ($event->ndrCase->reason ?? ''),
            'attempts' => (string) $event->ndrCase->attempts,
        ]);
    }

    /**
     * @return array{0: SalesOrder|null, 1: SellerOrder|null}
     */
    protected function orders(Shipment $shipment): array
    {
        $sellerOrder = SellerOrder::query()->find($shipment->seller_order_id);
        $order = $sellerOrder !== null ? SalesOrder::query()->with('customer')->find($sellerOrder->sales_order_id) : null;

        return [$order, $sellerOrder];
    }

    protected function trackUrl(Shipment $shipment): string
    {
        try {
            return frontRoute('tracking.show', ['awbOrOrderNo' => (string) $shipment->awb]);
        } catch (Throwable) {
            return (string) $shipment->tracking_url;
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function send(string $key, SalesOrder $order, array $data): void
    {
        try {
            $this->hub->event($key, $order->customer, $data + [
                'url' => frontRoute('customer.orders.show', ['orderNo' => $order->order_no]),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
