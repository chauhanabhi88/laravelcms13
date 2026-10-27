<?php

namespace Modules\Storefront\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Repositories\HomeSectionRepository;

class CacheHomeSectionDecorator extends BaseCacheDecorator implements HomeSectionRepository
{
    public function __construct(HomeSectionRepository $homeSection)
    {
        parent::__construct();
        $this->entityName = config('storefront.cache.home_section');
        $this->repository = $homeSection;
    }

    public function getFilters(Request $request, array $statusOptions, array $typeOptions): array
    {
        return $this->repository->getFilters($request, $statusOptions, $typeOptions);
    }

    public function pagination(Request $request): LengthAwarePaginator
    {
        return $this->repository->pagination($request);
    }

    public function filter(Request $request): Builder
    {
        return $this->repository->filter($request);
    }

    public function move(HomeSection $section, string $direction): void
    {
        $this->repository->move($section, $direction);
        $this->flushCacheFor($this->entityName);
    }

    public function nextSortOrder(): int
    {
        return $this->repository->nextSortOrder();
    }
}
