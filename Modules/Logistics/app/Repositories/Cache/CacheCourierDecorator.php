<?php

namespace Modules\Logistics\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Logistics\Repositories\CourierRepository;

/**
 * Grid and builder methods pass through (a Builder must never be cached).
 */
class CacheCourierDecorator extends BaseCacheDecorator implements CourierRepository
{
    public function __construct(CourierRepository $courier)
    {
        parent::__construct();
        $this->entityName = config('logistics.cache.courier');
        $this->repository = $courier;
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

    public function getOptions(bool $withPlaceholder = false, bool $all = false): array
    {
        return $this->remember(fn () => $this->repository->getOptions($withPlaceholder, $all));
    }
}
