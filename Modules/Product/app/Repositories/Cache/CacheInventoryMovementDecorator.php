<?php

namespace Modules\Product\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Product\Repositories\InventoryMovementRepository;

/**
 * Pass-through: the ledger is written by InventoryService and must always read live.
 */
class CacheInventoryMovementDecorator extends BaseCacheDecorator implements InventoryMovementRepository
{
    public function __construct(InventoryMovementRepository $inventoryMovement)
    {
        parent::__construct();
        $this->entityName = config('product.cache.inventory_movement');
        $this->repository = $inventoryMovement;
    }

    public function getFilters(Request $request): array
    {
        return $this->repository->getFilters($request);
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
