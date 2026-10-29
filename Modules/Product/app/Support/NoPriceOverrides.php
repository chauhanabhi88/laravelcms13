<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Modules\Product\Contracts\PriceResolver;

/**
 * Default PriceResolver: every variant sells at its own price.
 */
class NoPriceOverrides implements PriceResolver
{
    public function forVariants(array $variantIds): array
    {
        return [];
    }

    public function forProducts(array $productIds): array
    {
        return [];
    }
}
