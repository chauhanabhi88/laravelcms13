<?php

declare(strict_types=1);

namespace Modules\Product\Contracts;

use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Seller\Models\Seller;

/**
 * Deductions from a seller's sale: commission, platform / logistics fees and withholding
 * taxes. Bound to ZeroFeeCalculator until the Finance module (M17) binds its commission
 * engine.
 */
interface FeeCalculator
{
    /**
     * @return array<string, Money> fee code => amount deducted (commission, platform_fee, logistics, tcs, tds)
     */
    public function fees(Money $price, CatalogCategory $category, Seller $seller): array;
}
