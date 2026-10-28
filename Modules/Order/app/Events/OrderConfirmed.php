<?php

declare(strict_types=1);

namespace Modules\Order\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\SalesOrder;

/**
 * The order may now be fulfilled: COD placed (OTP verified when needed) or prepaid captured.
 * Its seller orders moved to pending_acceptance. Notifications, shipment planning (M13) and
 * ledger entries (M17) hang off this event. Fired once per order, after commit.
 */
class OrderConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public SalesOrder $order) {}
}
