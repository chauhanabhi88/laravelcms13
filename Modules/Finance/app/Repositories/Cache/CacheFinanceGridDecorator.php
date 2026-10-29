<?php

namespace Modules\Finance\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Finance\Repositories\FinanceGridRepository;

/**
 * Money moves only through the finance services, and grids must always show the live state,
 * so nothing here is cached.
 */
class CacheFinanceGridDecorator extends BaseCacheDecorator implements FinanceGridRepository
{
    public function __construct(FinanceGridRepository $grid)
    {
        parent::__construct();
        $this->entityName = (string) config('finance.cache.'.$grid->grid());
        $this->repository = $grid;
    }

    public function find($id)
    {
        return $this->repository->find($id);
    }

    public function grid(): string
    {
        return $this->repository->grid();
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
