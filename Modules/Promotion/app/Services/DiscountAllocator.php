<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Modules\Core\Support\Money;

/**
 * Spreads an order discount over the eligible lines in proportion to their value (FR-PRM-004),
 * so tax (GST on the price after the discount), refunds and seller settlements stay correct
 * per item. Exact in paise: the shares always sum to the discount; the rounding remainder goes
 * to the largest line (Money::allocate).
 */
class DiscountAllocator
{
    /**
     * @param  array<int, Money>  $lineValues  cart item (or order item) id => line value
     * @return array<int, Money> the same keys => share (lines without value get none)
     */
    public function allocate(Money $discount, array $lineValues): array
    {
        $weights = array_filter(array_map(fn (Money $value): int => max(0, $value->paise), $lineValues), fn (int $weight): bool => $weight > 0);

        if (! $discount->isPositive() || $weights === []) {
            return [];
        }

        return $discount->allocate($weights);
    }
}
