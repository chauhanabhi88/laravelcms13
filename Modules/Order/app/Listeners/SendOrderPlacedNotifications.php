<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Illuminate\Support\Facades\Route;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\Events\OrderConfirmed;
use Modules\Order\Models\SellerOrder;
use Throwable;

/**
 * Once an order is confirmed: the customer's "order placed" message and each seller's "new
 * order" alert (M09 catalogue: order.placed, seller.order.new). The hub queues the sends; a
 * failure is reported and never breaks the order.
 */
class SendOrderPlacedNotifications
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order->loadMissing(['customer', 'sellerOrders.seller', 'sellerOrders.items']);
        $latestDelivery = $order->sellerOrders->max('expected_delivery_at');

        try {
            if ($order->customer !== null) {
                $this->hub->event('order.placed', $order->customer, [
                    'order_no' => $order->order_no,
                    'amount' => $order->grand_total->format(),
                    'item_count' => $order->items_count,
                    'delivery_date' => $latestDelivery?->translatedFormat((string) config('storefront.delivery.date_format', 'D, j M')) ?? '',
                    'url' => Route::has('customer.orders.show') ? frontRoute('customer.orders.show', ['orderNo' => $order->order_no]) : $order->confirmationUrl(),
                ]);
            }
        } catch (Throwable $e) {
            report($e);
        }

        foreach ($order->sellerOrders as $sellerOrder) {
            /** @var SellerOrder $sellerOrder */
            if ($sellerOrder->status !== SellerOrder::STATUS_PENDING_ACCEPTANCE || $sellerOrder->seller === null) {
                continue;
            }

            try {
                $this->hub->eventForSeller('seller.order.new', $sellerOrder->seller, [
                    'order_no' => $sellerOrder->sub_order_no,
                    'item_count' => $sellerOrder->items->sum('quantity'),
                    'dispatch_by' => $sellerOrder->sla_dispatch_by?->translatedFormat('D, j M, g:i A') ?? '',
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
