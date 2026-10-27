<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\FeeCalculator;
use Modules\Seller\Models\Seller;

/**
 * Used until M17: the marketplace takes no commission and no fees.
 */
class ZeroFeeCalculator implements FeeCalculator
{
    public function fees(Money $price, CatalogCategory $category, Seller $seller): array
    {
        return [
            'commission' => Money::zero(),
            'platform_fee' => Money::zero(),
            'logistics' => Money::zero(),
            'tcs' => Money::zero(),
            'tds' => Money::zero(),
        ];
    }
}
