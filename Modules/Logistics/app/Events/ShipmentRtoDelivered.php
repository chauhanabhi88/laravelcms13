<?php

declare(strict_types=1);

namespace Modules\Logistics\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Logistics\Models\Shipment;

/**
 * The package is back at the seller's warehouse (fired after commit).
 */
class ShipmentRtoDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Shipment $shipment) {}
}
