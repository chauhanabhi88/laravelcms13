<?php

declare(strict_types=1);

namespace Modules\Returns\DTO;

use Modules\Core\Support\Money;

/**
 * What a return refunds (plan M15 §5.5): per item the unit and line amounts after the coupon
 * share, plus the package's delivery share when the seller is at fault.
 */
final readonly class RefundQuote
{
    /**
     * @param  array<int, Money>  $units  sales_order_item_id => unit refund
     * @param  array<int, Money>  $lines  sales_order_item_id => line refund
     */
    public function __construct(
        public array $units,
        public array $lines,
        public Money $delivery,
    ) {}

    public function itemsTotal(): Money
    {
        return array_reduce($this->lines, fn (Money $sum, Money $line): Money => $sum->plus($line), Money::zero());
    }

    public function total(): Money
    {
        return $this->itemsTotal()->plus($this->delivery);
    }
}
