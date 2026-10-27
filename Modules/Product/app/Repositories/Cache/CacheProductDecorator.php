<?php

namespace Modules\Product\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Product\Models\Product;
use Modules\Product\Repositories\ProductRepository;

/**
 * Pass-through: product rows change through the factory, inventory and moderation
 * services, which write models directly, so cached reads here would go stale. The admin
 * grids and the QC queue read live.
 */
class CacheProductDecorator extends BaseCacheDecorator implements ProductRepository
{
    public function __construct(ProductRepository $product)
    {
        parent::__construct();
        $this->entityName = config('product.cache.product');
        $this->repository = $product;
    }

    public function getFilters(Request $request, string $grid): array
    {
        return $this->repository->getFilters($request, $grid);
    }

    public function pagination(Request $request, string $grid): LengthAwarePaginator
    {
        return $this->repository->pagination($request, $grid);
    }

    public function filter(Request $request, string $grid): Builder
    {
        return $this->repository->filter($request, $grid);
    }

    public function findWithDetails(int $id): ?Product
    {
        return $this->repository->findWithDetails($id);
    }

    public function qcQueue(int $limit): Collection
    {
        return $this->repository->qcQueue($limit);
    }

    public function exportRows(Request $request): array
    {
        return $this->repository->exportRows($request);
    }
}
