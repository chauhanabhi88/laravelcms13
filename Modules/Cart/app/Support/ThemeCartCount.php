<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Modules\Cart\DTO\CartOwner;
use Modules\Cart\Services\CartService;
use Modules\Theme\Contracts\CartCountProvider;

/**
 * The header / bottom-nav cart badge: units in the visitor's cart. Never creates a cart.
 */
class ThemeCartCount implements CartCountProvider
{
    public function __construct(protected CartService $carts) {}

    public function count(): int
    {
        try {
            return $this->carts->count(CartOwner::fromRequest(request()));
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }
}
