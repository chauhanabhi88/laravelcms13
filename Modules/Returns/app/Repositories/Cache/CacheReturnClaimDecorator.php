<?php

namespace Modules\Returns\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Returns\Repositories\ReturnClaimRepository;

/**
 * Claims change through ReturnClaimService only, so nothing here is cached.
 */
class CacheReturnClaimDecorator extends BaseCacheDecorator implements ReturnClaimRepository
{
    public function __construct(ReturnClaimRepository $returnClaim)
    {
        parent::__construct();
        $this->entityName = config('returns.cache.return_claim');
        $this->repository = $returnClaim;
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
