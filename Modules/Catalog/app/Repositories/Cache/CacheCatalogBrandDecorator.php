<?php

namespace Modules\Catalog\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Catalog\Repositories\CatalogBrandRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCatalogBrandDecorator extends BaseCacheDecorator implements CatalogBrandRepository
{
    public function __construct(CatalogBrandRepository $brand)
    {
        parent::__construct();
        $this->entityName = config('catalog.cache.brand');
        $this->repository = $brand;
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

    public function searchEnabled(?string $term, int $limit): Collection
    {
        return $this->remember(fn () => $this->repository->searchEnabled($term, $limit));
    }
}
