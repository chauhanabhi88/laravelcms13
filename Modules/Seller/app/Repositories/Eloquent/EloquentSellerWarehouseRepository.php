<?php

namespace Modules\Seller\Repositories\Eloquent;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Seller\Repositories\SellerWarehouseRepository;

class EloquentSellerWarehouseRepository extends EloquentBaseRepository implements SellerWarehouseRepository
{
    public function forSeller(int $sellerId): Collection
    {
        return $this->model->newQuery()
            ->where('seller_id', $sellerId)
            ->orderBy('is_default')
            ->orderBy('name')
            ->get();
    }
}
