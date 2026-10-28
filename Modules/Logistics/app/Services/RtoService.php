<?php

declare(strict_types=1);

namespace Modules\Logistics\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Exceptions\ShipmentException;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Payment\Services\PaymentService;
use Modules\Product\Services\InventoryService;
use Throwable;

/**
 * Return to origin (FR-LOG-009, plan M13 §5.7). Tracking already moves the seller order to
 * rto_initiated / rto_delivered (and M12 counts the RTO against the customer's COD risk on
 * rto_initiated). Here:
 * - onDelivered(): an order that is now fully closed no longer waits for cash on delivery,
 *   so its open COD payment is cancelled;
 * - confirmReceipt(): the seller (M14) confirms the package's condition: restock the items or
 *   raise a claim. M17 posts the ledger reversal and RTO freight from the shipment charges.
 */
class RtoService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly PaymentService $payments,
    ) {}

    public function onDelivered(Shipment $shipment): void
    {
        $shipment->loadMissing('sellerOrder.salesOrder');
        $order = $shipment->sellerOrder->salesOrder;

        if ($order->isCod() && $order->status === SalesOrder::STATUS_CANCELLED) {
            try {
                $this->payments->cancelForOrder($order->id, 'rto_delivered');
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @throws ShipmentException
     */
    public function confirmReceipt(Shipment $shipment, bool $restock, ?Model $actor = null): Shipment
    {
        DB::transaction(function () use ($shipment, $restock, $actor): void {
            $locked = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($locked->status !== Shipment::STATUS_RTO_DELIVERED || $locked->rto_restocked !== null) {
                throw new ShipmentException(trans('logistics::shipment.messages.rto_not_receivable'), 'invalid_state');
            }

            if ($restock) {
                $items = SalesOrderItem::query()
                    ->where('seller_order_id', $locked->seller_order_id)
                    ->where('status', '!=', SalesOrderItem::STATUS_CANCELLED)
                    ->orderBy('variant_id')
                    ->get(['id', 'variant_id', 'quantity', 'cancelled_qty']);

                foreach ($items as $item) {
                    $qty = $item->quantity - $item->cancelled_qty;

                    if ($qty > 0) {
                        $this->inventory->restock($item->variant_id, $qty, $locked, $actor, 'rto');
                    }
                }
            }

            $locked->forceFill(['rto_restocked' => (int) config($restock ? 'core.yes' : 'core.no')])->save();
        });

        return $shipment->refresh();
    }
}
