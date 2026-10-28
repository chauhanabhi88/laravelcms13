<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Illuminate\Support\Facades\Route;
use Modules\Notification\Services\NotificationHub;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Services\OrderCancellationService;
use Throwable;

/**
 * Tells the customer about a cancellation (M09 order.cancelled). Orders that never got past
 * payment are skipped: the buyer saw the payment page fail and there was nothing to ship.
 */
class SendOrderCancelledNotification
{
    public function __construct(private readonly NotificationHub $hub) {}

    public function handle(OrderCancelled $event): void
    {
        $order = $event->order->loadMissing('customer');

        if ($order->customer === null || ($event->cancelledBy === OrderCancellationService::BY_SYSTEM && ! $this->wasConfirmed($event))) {
            return;
        }

        try {
            $this->hub->event('order.cancelled', $order->customer, [
                'order_no' => $order->order_no,
                'reason' => $event->reason,
                'url' => Route::has('customer.orders.show') ? frontRoute('customer.orders.show', ['orderNo' => $order->order_no]) : $order->confirmationUrl(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function wasConfirmed(OrderCancelled $event): bool
    {
        return $event->sellerOrder->statusHistories()->where('to_status', SellerOrder::STATUS_PENDING_ACCEPTANCE)->exists();
    }
}
