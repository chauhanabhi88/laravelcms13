<?php

namespace Modules\Cart\Repositories\Cache;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Cart\Repositories\CartRepository;
use Modules\Core\Repositories\Cache\BaseCacheDecorator;

/**
 * Carts change on every storefront click through CartService (not this repository), so
 * nothing here is cached: every read goes to the database.
 */
class CacheCartDecorator extends BaseCacheDecorator implements CartRepository
{
    public function __construct(CartRepository $cart)
    {
        parent::__construct();
        $this->entityName = config('cart.cache.cart');
        $this->repository = $cart;
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
