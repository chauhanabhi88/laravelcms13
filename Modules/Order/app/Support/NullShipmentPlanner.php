<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Order\Contracts\ShipmentPlanner;
use Modules\Order\Models\SellerOrder;

/**
 * No courier integration before M13: nothing to plan.
 */
class NullShipmentPlanner implements ShipmentPlanner
{
    public function plan(SellerOrder $sellerOrder): void {}
}
