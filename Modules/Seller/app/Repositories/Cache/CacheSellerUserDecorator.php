<?php

namespace Modules\Seller\Repositories\Cache;

use Illuminate\Support\Collection;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Seller\Repositories\SellerUserRepository;

/**
 * Pass-through: login rows carry passwords and statuses that must never be served stale.
 */
class CacheSellerUserDecorator extends BaseCacheDecorator implements SellerUserRepository
{
    public function __construct(SellerUserRepository $sellerUser)
    {
        parent::__construct();
        $this->entityName = config('seller.cache.seller_user');
        $this->repository = $sellerUser;
    }

    public function forSeller(int $sellerId): Collection
    {
        return $this->repository->forSeller($sellerId);
    }
}
