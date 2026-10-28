<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Services\OrderFulfillmentService;

/**
 * Hourly (FR-FUL-003): one seller.sla.warning per order not yet packed with 75% of its
 * dispatch window used.
 */
class WarnSlaBreaches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(OrderFulfillmentService $fulfilment): int
    {
        return $fulfilment->warnSlaBreaches();
    }
}
