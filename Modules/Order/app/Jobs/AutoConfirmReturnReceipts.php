<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Services\OrderFulfillmentService;

/**
 * Daily (plan M14 §5.7): RTO packages the seller has not confirmed within the configured days
 * are confirmed as received in good condition and restocked.
 */
class AutoConfirmReturnReceipts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(OrderFulfillmentService $fulfilment): int
    {
        return $fulfilment->autoConfirmReceipts();
    }
}
