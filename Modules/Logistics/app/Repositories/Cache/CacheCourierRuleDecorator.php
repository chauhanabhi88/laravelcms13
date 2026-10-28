<?php

namespace Modules\Logistics\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Logistics\Repositories\CourierRuleRepository;

/**
 * Grid and builder methods pass through (a Builder must never be cached).
 */
class CacheCourierRuleDecorator extends BaseCacheDecorator implements CourierRuleRepository
{
    public function __construct(CourierRuleRepository $courierRule)
    {
        parent::__construct();
        $this->entityName = config('logistics.cache.courier_rule');
        $this->repository = $courierRule;
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
