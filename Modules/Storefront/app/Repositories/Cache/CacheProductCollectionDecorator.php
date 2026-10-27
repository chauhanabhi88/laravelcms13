<?php

namespace Modules\Storefront\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Storefront\Repositories\ProductCollectionRepository;

class CacheProductCollectionDecorator extends BaseCacheDecorator implements ProductCollectionRepository
{
    public function __construct(ProductCollectionRepository $collection)
    {
        parent::__construct();
        $this->entityName = config('storefront.cache.product_collection');
        $this->repository = $collection;
    }

    public function getFilters(Request $request, array $statusOptions): array
    {
        return $this->repository->getFilters($request, $statusOptions);
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->repository->pagination($request);
    }

    public function filter(Request $request): Builder
    {
        return $this->repository->filter($request);
    }

    public function getOptions(bool $withPlaceholder = false): array
    {
        return $this->remember(fn () => $this->repository->getOptions($withPlaceholder));
    }
}
