<?php

declare(strict_types=1);

namespace Modules\Theme\Contracts;

/**
 * Product ids the current customer has wishlisted, to seed the Alpine wishlist store.
 * Rebound by the Customer wishlist (M08).
 */
interface WishlistProvider
{
    /**
     * @return array<int, int|string>
     */
    public function ids(): array;
}
