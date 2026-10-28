<?php

namespace Modules\Order\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Order\Repositories\SalesOrderRepository;

/**
 * Orders change through the order services (placement, payments, cancellations, couriers),
 * not this repository, so nothing here is cached: every read goes to the database.
 */
class CacheSalesOrderDecorator extends BaseCacheDecorator implements SalesOrderRepository
{
    public function __construct(SalesOrderRepository $salesOrder)
    {
        parent::__construct();
        $this->entityName = config('order.cache.sales_order');
        $this->repository = $salesOrder;
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
