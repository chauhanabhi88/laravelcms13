<?php

namespace Modules\Seller\Repositories\Cache;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Seller\Repositories\SellerWarehouseRepository;

/**
 * Pass-through: warehouses are written by WarehouseService, so reads stay live.
 */
class CacheSellerWarehouseDecorator extends BaseCacheDecorator implements SellerWarehouseRepository
{
    public function __construct(SellerWarehouseRepository $warehouse)
    {
        parent::__construct();
        $this->entityName = config('seller.cache.warehouse');
        $this->repository = $warehouse;
    }

    public function forSeller(int $sellerId): Collection
    {
        return $this->repository->forSeller($sellerId);
    }
}
