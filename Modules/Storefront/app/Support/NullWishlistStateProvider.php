<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Modules\Storefront\Contracts\WishlistStateProvider;
use Modules\Theme\Contracts\WishlistProvider;

/**
 * Reads the header's wishlist ids (Theme WishlistProvider) but offers no toggle endpoint, so
 * the product page hides its heart until the Customer wishlist (M08) rebinds this contract.
 */
class NullWishlistStateProvider implements WishlistStateProvider
{
    public function __construct(protected WishlistProvider $wishlist) {}

    public function isWishlisted(int $productId): bool
    {
        return in_array((string) $productId, array_map('strval', $this->wishlist->ids()), true);
    }

    public function toggleUrl(): ?string
    {
        return null;
    }
}
