<?php

declare(strict_types=1);

namespace Modules\Storefront\Contracts;

/**
 * Wishlist state of the product page heart. Until the Customer wishlist (M08) binds a real
 * provider, toggleUrl() is null and the heart stays hidden.
 */
interface WishlistStateProvider
{
    public function isWishlisted(int $productId): bool;

    /**
     * Endpoint the heart posts to ({product_id}), or null when wishlists are unavailable.
     */
    public function toggleUrl(): ?string;
}
