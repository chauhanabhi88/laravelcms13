<?php

declare(strict_types=1);

namespace Modules\Product\Exceptions;

/**
 * A variant cannot cover the requested quantity. Nothing of the operation was written.
 */
class InsufficientStock extends ProductException
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(trans('product::product.messages.insufficient_stock', [
            'variant' => $variantId,
            'requested' => $requested,
            'available' => $available,
        ]));
    }
}
