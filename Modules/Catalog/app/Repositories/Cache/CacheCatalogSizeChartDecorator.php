<?php

namespace Modules\Catalog\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Catalog\Repositories\CatalogSizeChartRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

class CacheCatalogSizeChartDecorator extends BaseCacheDecorator implements CatalogSizeChartRepository
{
    public function __construct(CatalogSizeChartRepository $sizeChart)
    {
        parent::__construct();
        $this->entityName = config('catalog.cache.size_chart');
        $this->repository = $sizeChart;
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
