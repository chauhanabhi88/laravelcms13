<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Seller\Models\SellerWarehouse;

/**
 * Pickup location code of a warehouse for couriers without a location API.
 */
final class ManualPickupLocation
{
    public static function code(SellerWarehouse $warehouse): string
    {
        return $warehouse->courier_pickup_ref ?: 'WH-'.$warehouse->id;
    }
}
