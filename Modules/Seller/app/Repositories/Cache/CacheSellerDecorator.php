<?php

namespace Modules\Seller\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;
use Modules\Seller\Models\Seller;
use Modules\Seller\Repositories\SellerRepository;

/**
 * Pass-through: seller rows change through the onboarding / verification services, which
 * write models directly, so cached reads here would go stale. Grids and the store page read live.
 */
class CacheSellerDecorator extends BaseCacheDecorator implements SellerRepository
{
    public function __construct(SellerRepository $seller)
    {
        parent::__construct();
        $this->entityName = config('seller.cache.seller');
        $this->repository = $seller;
    }

    public function getFilters(Request $request, string $grid): array
    {
        return $this->repository->getFilters($request, $grid);
    }

    public function pagination(Request $request, string $grid): LengthAwarePaginator
    {
        return $this->repository->pagination($request, $grid);
    }

    public function filter(Request $request, string $grid): Builder
    {
        return $this->repository->filter($request, $grid);
    }

    public function findWithDetails(int $id): ?Seller
    {
        return $this->repository->findWithDetails($id);
    }

    public function findPublicBySlug(string $slug): ?Seller
    {
        return $this->repository->findPublicBySlug($slug);
    }

    public function getGstStatusOptions(bool $withPlaceholder = false): array
    {
        return $this->repository->getGstStatusOptions($withPlaceholder);
    }
}
