<?php

declare(strict_types=1);

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\DTO\FulfilmentResult;
use Modules\Order\Services\OrderFulfillmentService;

/**
 * Every 10 minutes (plan M14 §5.2): accepts the pending orders of sellers who switched on
 * auto-accept, and books their shipments.
 */
class AutoAcceptOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $limit = 200) {}

    public function handle(OrderFulfillmentService $fulfilment): FulfilmentResult
    {
        return $fulfilment->autoAccept($this->limit);
    }
}
