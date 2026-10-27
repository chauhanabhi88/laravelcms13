<?php

namespace Modules\Catalog\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Repositories\CatalogCategoryRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCatalogCategoryDecorator extends BaseCacheDecorator implements CatalogCategoryRepository
{
    public function __construct(CatalogCategoryRepository $category)
    {
        parent::__construct();
        $this->entityName = config('catalog.cache.category');
        $this->repository = $category;
    }

    public function getFilters(Request $request, array $statusOptions): array
    {
        return $this->repository->getFilters($request, $statusOptions);
    }

    /**
     * Not cached: grid entries are per user, filter and page (see BaseCacheDecorator::paginate()).
     */
    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->repository->pagination($request);
    }

    public function filter(Request $request): Builder
    {
        return $this->repository->filter($request);
    }

    public function findEnabledBySlug(string $slug): ?CatalogCategory
    {
        return $this->remember(fn () => $this->repository->findEnabledBySlug($slug));
    }
}
