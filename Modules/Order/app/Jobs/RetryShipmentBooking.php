<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Services\OrderFulfillmentService;

/**
 * Books the AWB of an accepted seller order again after a courier failure (plan M14 §5.2).
 * The service counts the failures and queues the next attempt with the configured backoff
 * (order.fulfilment.booking_backoff), then alerts Logistics Ops.
 */
class RetryShipmentBooking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $sellerOrderId) {}

    public function handle(OrderFulfillmentService $fulfilment): void
    {
        $fulfilment->retryBooking($this->sellerOrderId);
    }
}
