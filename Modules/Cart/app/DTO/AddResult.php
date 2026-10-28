<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;

/**
 * Outcome of putting a variant in a cart. $capped is true when the quantity was lowered to
 * the per-line maximum or to the units available.
 */
final readonly class AddResult
{
    public function __construct(
        public Cart $cart,
        public CartItem $item,
        public int $requested,
        public bool $capped,
        public bool $created,
    ) {}
}
