<?php

namespace Modules\Seller\Repositories;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\BaseRepository;
use Modules\Seller\Models\SellerWarehouse;

interface SellerWarehouseRepository extends BaseRepository
{
    /**
     * A seller's warehouses, default first.
     *
     * @return Collection<int, SellerWarehouse>
     */
    public function forSeller(int $sellerId): Collection;
}
