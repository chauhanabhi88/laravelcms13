<?php

declare(strict_types=1);

namespace Modules\Order\Contracts;

use Modules\Order\Models\SellerOrder;

/**
 * Prepares the forward shipment of a confirmed seller order (courier rule, package). Bound to
 * NullShipmentPlanner (bindIf) until the Logistics module (M13) binds its own.
 */
interface ShipmentPlanner
{
    public function plan(SellerOrder $sellerOrder): void;
}
