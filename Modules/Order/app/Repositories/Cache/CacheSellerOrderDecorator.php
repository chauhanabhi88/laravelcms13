<?php

namespace Modules\Order\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Order\Repositories\SellerOrderRepository;

/**
 * Seller orders change through the order services, not this repository, so nothing here is
 * cached: every read goes to the database.
 */
class CacheSellerOrderDecorator extends BaseCacheDecorator implements SellerOrderRepository
{
    public function __construct(SellerOrderRepository $sellerOrder)
    {
        parent::__construct();
        $this->entityName = config('order.cache.seller_order');
        $this->repository = $sellerOrder;
    }

    public function find($id)
    {
        return $this->repository->find($id);
    }

    public function getFilters(Request $request, array $options): array
    {
        return $this->repository->getFilters($request, $options);
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->repository->pagination($request);
    }

    public function filter(Request $request): Builder
    {
        return $this->repository->filter($request);
    }
}
