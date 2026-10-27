<?php

declare(strict_types=1);

namespace Modules\Customer\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Exceptions\WishlistException;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerWishlist;
use Modules\Customer\Support\CustomerSettings;
use Modules\Product\Models\Product;

/**
 * The customer wishlist (FR-CUS-006). Product ids are loaded once per request and memoised on
 * the request attributes (a scoped/singleton cache would leak between requests in tests and
 * under Octane); every write clears the memo.
 */
class WishlistService
{
    private const MEMO_KEY = 'customer.wishlist_ids.';

    /**
     * Wishlisted product ids, newest first.
     *
     * @return list<int>
     */
    public function ids(Customer|int $customer): array
    {
        $customerId = $customer instanceof Customer ? (int) $customer->getKey() : $customer;
        $attributes = request()->attributes;
        $key = self::MEMO_KEY.$customerId;

        if (! $attributes->has($key)) {
            $attributes->set($key, CustomerWishlist::query()
                ->where('customer_id', $customerId)
                ->orderByDesc('created_at')
                ->orderByDesc('product_id')
                ->pluck('product_id')
                ->map(fn ($id): int => (int) $id)
                ->all());
        }

        return $attributes->get($key);
    }

    public function has(Customer $customer, int $productId): bool
    {
        return in_array($productId, $this->ids($customer), true);
    }

    public function count(Customer $customer): int
    {
        return count($this->ids($customer));
    }

    /**
     * Put a product in or out of the wishlist; returns the final state.
     *
     * @throws WishlistException when adding an unavailable product or past the size limit
     */
    public function set(Customer $customer, int $productId, bool $wishlisted): bool
    {
        if (! $wishlisted) {
            $this->remove($customer, $productId);

            return false;
        }

        if ($this->has($customer, $productId)) {
            return true;
        }

        if (! $this->productAvailable($productId)) {
            throw new WishlistException(trans('customer::customer_front.wishlist.unavailable'));
        }

        if ($this->count($customer) >= CustomerSettings::maxWishlistItems()) {
            throw new WishlistException(trans('customer::customer_front.wishlist.full', ['max' => CustomerSettings::maxWishlistItems()]));
        }

        DB::table('customer_wishlist')->insertOrIgnore([
            'customer_id' => $customer->getKey(),
            'product_id' => $productId,
            'created_at' => Carbon::now(),
        ]);
        $this->forget($customer);

        return true;
    }

    /**
     * @throws WishlistException
     */
    public function toggle(Customer $customer, int $productId): bool
    {
        return $this->set($customer, $productId, ! $this->has($customer, $productId));
    }

    public function remove(Customer $customer, int $productId): void
    {
        DB::table('customer_wishlist')->where('customer_id', $customer->getKey())->where('product_id', $productId)->delete();
        $this->forget($customer);
    }

    public function clear(Customer $customer): void
    {
        DB::table('customer_wishlist')->where('customer_id', $customer->getKey())->delete();
        $this->forget($customer);
    }

    /**
     * One page of wishlisted product ids, newest first.
     *
     * @return LengthAwarePaginator<int, int>
     */
    public function paginate(Customer $customer, int $perPage, int $page): LengthAwarePaginator
    {
        $ids = $this->ids($customer);

        return new LengthAwarePaginator(
            array_slice($ids, ($page - 1) * $perPage, $perPage),
            count($ids),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    protected function productAvailable(int $productId): bool
    {
        if (class_exists(Product::class)) {
            return Product::query()->visibleOnStorefront()->whereKey($productId)->exists();
        }

        return DB::table('product')->where('id', $productId)->exists();
    }

    protected function forget(Customer|int $customer): void
    {
        $customerId = $customer instanceof Customer ? (int) $customer->getKey() : $customer;

        request()->attributes->remove(self::MEMO_KEY.$customerId);
    }
}
