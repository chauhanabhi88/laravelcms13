<?php

declare(strict_types=1);

namespace Modules\Logistics\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Logistics\Models\Shipment;

/**
 * The package reached the customer (fired after commit).
 */
class ShipmentDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Shipment $shipment) {}
}
