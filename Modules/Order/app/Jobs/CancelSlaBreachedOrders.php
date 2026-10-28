<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Services\OrderFulfillmentService;

/**
 * Hourly (FR-FUL-003): cancels seller orders not picked up by their dispatch deadline plus the
 * grace period (stock released, prepaid refunded, penalty hook).
 */
class CancelSlaBreachedOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(OrderFulfillmentService $fulfilment): int
    {
        return $fulfilment->cancelSlaBreached();
    }
}
