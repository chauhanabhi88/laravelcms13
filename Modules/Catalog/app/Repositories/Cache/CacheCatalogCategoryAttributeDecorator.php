<?php

namespace Modules\Catalog\Repositories\Cache;

use Illuminate\Support\Collection;
use Modules\Catalog\Repositories\CatalogCategoryAttributeRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCatalogCategoryAttributeDecorator extends BaseCacheDecorator implements CatalogCategoryAttributeRepository
{
    public function __construct(CatalogCategoryAttributeRepository $categoryAttribute)
    {
        parent::__construct();
        $this->entityName = config('catalog.cache.category_attribute');
        $this->repository = $categoryAttribute;
    }

    public function forCategory(int $categoryId): Collection
    {
        return $this->remember(fn () => $this->repository->forCategory($categoryId));
    }

    public function forCategories(array $categoryIds): Collection
    {
        return $this->remember(fn () => $this->repository->forCategories($categoryIds));
    }

    public function sync(int $categoryId, array $rows): void
    {
        $this->flushCacheFor($this->entityName);

        $this->repository->sync($categoryId, $rows);
    }
}
