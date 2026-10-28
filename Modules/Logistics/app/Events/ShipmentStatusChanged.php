<?php

declare(strict_types=1);

namespace Modules\Logistics\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Logistics\Models\Shipment;

/**
 * A shipment moved from $from to $to (booking, a courier scan or Logistics Ops). Fired after
 * commit; drives the customer notifications (M09).
 */
class ShipmentStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Shipment $shipment,
        public string $from,
        public string $to,
    ) {}
}
