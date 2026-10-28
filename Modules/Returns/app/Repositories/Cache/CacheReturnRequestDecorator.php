<?php

namespace Modules\Returns\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Returns\Repositories\ReturnRequestRepository;

/**
 * Returns change through ReturnService, courier scans and refunds, so nothing here is cached.
 */
class CacheReturnRequestDecorator extends BaseCacheDecorator implements ReturnRequestRepository
{
    public function __construct(ReturnRequestRepository $returnRequest)
    {
        parent::__construct();
        $this->entityName = config('returns.cache.return_request');
        $this->repository = $returnRequest;
    }

    public function find($id)
    {
        return $this->repository->find($id);
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
