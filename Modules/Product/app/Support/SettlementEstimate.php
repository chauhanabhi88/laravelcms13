<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Modules\Core\Support\Money;

/**
 * What a seller receives for one unit sold at $price (price & earnings calculator, M05).
 */
final readonly class SettlementEstimate
{
    /**
     * @param  array<string, Money>  $fees  fee code => amount deducted
     */
    public function __construct(
        public Money $price,
        public array $fees,
        public Money $net,
    ) {}

    public function totalFees(): Money
    {
        return array_reduce($this->fees, fn (Money $total, Money $fee): Money => $total->plus($fee), Money::zero());
    }

    /**
     * @return array{price: string, fees: array<string, string>, total_fees: string, net: string}
     */
    public function toArray(): array
    {
        return [
            'price' => $this->price->toDecimal(),
            'fees' => array_map(fn (Money $fee): string => $fee->toDecimal(), $this->fees),
            'total_fees' => $this->totalFees()->toDecimal(),
            'net' => $this->net->toDecimal(),
        ];
    }
}
