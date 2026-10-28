<?php

namespace Modules\Logistics\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Logistics\Repositories\PickupBatchRepository;

/**
 * Grid and builder methods pass through (a Builder must never be cached).
 */
class CachePickupBatchDecorator extends BaseCacheDecorator implements PickupBatchRepository
{
    public function __construct(PickupBatchRepository $pickupBatch)
    {
        parent::__construct();
        $this->entityName = config('logistics.cache.pickup_batch');
        $this->repository = $pickupBatch;
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
