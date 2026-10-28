<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SalesOrder;

/**
 * Every seller order of the order is completed (delivered and past its return window).
 */
class OrderCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public SalesOrder $order) {}
}
