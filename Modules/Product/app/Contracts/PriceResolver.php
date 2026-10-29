<?php

declare(strict_types=1);

namespace Modules\Product\Contracts;

use Modules\Product\DTO\PriceOverride;

/**
 * Time-boxed selling-price overrides on variants (flash deals, M18). Every place that shows or
 * charges a variant's selling price reads it through here: product cards, the product page,
 * cart lines and place-order. Bound with bindIf to NoPriceOverrides; the Promotion module
 * rebinds it.
 *
 * Only variants priced BELOW their own selling price right now are returned, so callers use
 * `$overrides[$variant->id]->price ?? $variant->price`.
 */
interface PriceResolver
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, PriceOverride> variant id => override
     */
    public function forVariants(array $variantIds): array;

    /**
     * The lowest override per product (for cards).
     *
     * @param  list<int>  $productIds
     * @return array<int, PriceOverride> product id => override
     */
    public function forProducts(array $productIds): array;
}
