<?php

declare(strict_types=1);

namespace Modules\Customer\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Customer\Models\Customer;
use Modules\Customer\Services\WishlistService;
use Modules\Storefront\Contracts\WishlistStateProvider;

/**
 * The product page heart (Storefront contract, M07). The heart shows for guests too: a guest's
 * click gets a 401 from the toggle endpoint and opens the login drawer.
 */
class StorefrontWishlistProvider implements WishlistStateProvider
{
    public function __construct(protected WishlistService $wishlist) {}

    public function isWishlisted(int $productId): bool
    {
        $customer = Auth::guard('customer')->user();

        return $customer instanceof Customer && in_array($productId, $this->wishlist->ids($customer), true);
    }

    public function toggleUrl(): ?string
    {
        return Route::has('customer.wishlist.toggle') ? frontRoute('customer.wishlist.toggle') : null;
    }
}
