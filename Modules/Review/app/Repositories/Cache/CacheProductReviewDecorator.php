<?php

namespace Modules\Review\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Review\Repositories\ProductReviewRepository;

/**
 * Reviews change through the Review services only (votes and reports change them every
 * minute), so nothing here is cached.
 */
class CacheProductReviewDecorator extends BaseCacheDecorator implements ProductReviewRepository
{
    public function __construct(ProductReviewRepository $productReview)
    {
        parent::__construct();
        $this->entityName = config('review.cache.product_review');
        $this->repository = $productReview;
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
