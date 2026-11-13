<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\Logistics\Models\NdrCase;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SellerOrder;

/**
 * Failed-delivery cases of a package as the customer sees them: the open case they can still
 * answer, or the latest one with the answer they gave.
 */
class DeliveryIssues
{
    public function openFor(SellerOrder $sellerOrder): ?NdrCase
    {
        return $this->query($sellerOrder)->open()->first();
    }

    public function latestFor(SellerOrder $sellerOrder): ?NdrCase
    {
        return $this->query($sellerOrder)->first();
    }

    /**
     * @return Builder<NdrCase>
     */
    protected function query(SellerOrder $sellerOrder): Builder
    {
        return NdrCase::query()
            ->whereIn('shipment_id', Shipment::query()->forward()->where('seller_order_id', $sellerOrder->id)->select('id'))
            ->latest('id');
    }
}
