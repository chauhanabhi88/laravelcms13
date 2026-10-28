<?php

declare(strict_types=1);

namespace Modules\Logistics\Listeners;

use Modules\Logistics\Events\ShipmentDelivered;
use Modules\Logistics\Events\ShipmentRtoDelivered;
use Modules\Order\Models\SalesOrder;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\PaymentService;
use Throwable;

/**
 * Cash on delivery is collected once the whole order has reached the customer (plan M13 §5.4):
 * when a package is delivered (or another one comes back, closing the order) and the order's
 * derived status is delivered, its pending COD payment becomes "collected" (M11). The payment
 * covers the whole order, so a multi-package order waits for its last package.
 */
class MarkCodCollectedOnDelivered
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(ShipmentDelivered|ShipmentRtoDelivered $event): void
    {
        $event->shipment->loadMissing('sellerOrder');
        $order = SalesOrder::query()->find($event->shipment->sellerOrder?->sales_order_id);

        if ($order === null || ! $order->isCod() || ! in_array($order->status, [SalesOrder::STATUS_DELIVERED, SalesOrder::STATUS_COMPLETED], true)) {
            return;
        }

        $payment = Payment::query()
            ->forOrder($order->id)
            ->where('method', Payment::METHOD_COD)
            ->where('status', Payment::STATUS_PENDING)
            ->orderByDesc('id')
            ->first();

        if ($payment === null) {
            return;
        }

        try {
            $this->payments->markCodCollected($payment);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
