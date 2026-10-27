<?php

declare(strict_types=1);

namespace Modules\Customer\Support;

use Illuminate\Support\Facades\Auth;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Theme\Contracts\WishlistProvider;

/**
 * Seeds the header's Alpine wishlist store and the product card hearts (Theme contract).
 * Guests have no wishlist.
 */
class ThemeWishlistProvider implements WishlistProvider
{
    public function __construct(protected WishlistService $wishlist) {}

    /**
     * @return array<int, int>
     */
    public function ids(): array
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer ? $this->wishlist->ids($customer) : [];
    }
}
