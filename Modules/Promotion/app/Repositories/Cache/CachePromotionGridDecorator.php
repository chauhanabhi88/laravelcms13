<?php

namespace Modules\Promotion\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Promotion\Repositories\PromotionGridRepository;

/**
 * Usage counts and deal units change with every order, so the grids always read live data and
 * nothing here is cached.
 */
class CachePromotionGridDecorator extends BaseCacheDecorator implements PromotionGridRepository
{
    public function __construct(PromotionGridRepository $grid)
    {
        parent::__construct();
        $this->entityName = (string) config('promotion.cache.'.$grid->grid());
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
