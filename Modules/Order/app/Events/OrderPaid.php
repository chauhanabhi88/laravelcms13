<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SalesOrder;
use Modules\Payment\Models\Payment;

/**
 * The prepaid payment of an order was captured. Fired after commit.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public SalesOrder $order, public Payment $payment) {}
}
