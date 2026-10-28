<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Core\Support\Money;

/**
 * The cart price summary (FR-CRT-004): total MRP, discount on MRP, subtotal (selling prices),
 * then the adjustments (delivery, COD fee, coupon). Immutable: adjustments return a new copy.
 */
final readonly class Totals
{
    /**
     * @param  list<TotalsAdjustment>  $adjustments
     */
    public function __construct(
        public Money $totalMrp,
        public Money $discount,
        public Money $subtotal,
        public int $itemCount,
        public array $adjustments = [],
    ) {}

    public static function empty(): self
    {
        return new self(Money::zero(), Money::zero(), Money::zero(), 0);
    }

    public function withAdjustment(TotalsAdjustment $adjustment): self
    {
        $adjustments = array_values(array_filter($this->adjustments, fn (TotalsAdjustment $existing): bool => $existing->code !== $adjustment->code));
        $adjustments[] = $adjustment;

        return new self($this->totalMrp, $this->discount, $this->subtotal, $this->itemCount, $adjustments);
    }

    public function adjustment(string $code): ?TotalsAdjustment
    {
        foreach ($this->adjustments as $adjustment) {
            if ($adjustment->code === $code) {
                return $adjustment;
            }
        }

        return null;
    }

    public function deliveryCharge(): Money
    {
        return $this->adjustment(TotalsAdjustment::DELIVERY)?->amount ?? Money::zero();
    }

    public function codFee(): Money
    {
        return $this->adjustment(TotalsAdjustment::COD_FEE)?->amount ?? Money::zero();
    }

    /**
     * Subtotal plus every adjustment, never below zero.
     */
    public function grandTotal(): Money
    {
        $total = array_reduce($this->adjustments, fn (Money $sum, TotalsAdjustment $adjustment): Money => $sum->plus($adjustment->amount), $this->subtotal);

        return $total->isNegative() ? Money::zero() : $total;
    }

    /**
     * Discount on MRP plus every discount adjustment ("You will save ₹X on this order").
     */
    public function youSave(): Money
    {
        return array_reduce(
            $this->adjustments,
            fn (Money $sum, TotalsAdjustment $adjustment): Money => $adjustment->isDiscount() ? $sum->minus($adjustment->amount) : $sum,
            $this->discount,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'item_count' => $this->itemCount,
            'total_mrp' => $this->totalMrp->toDecimal(),
            'discount' => $this->discount->toDecimal(),
            'subtotal' => $this->subtotal->toDecimal(),
            'adjustments' => array_map(fn (TotalsAdjustment $adjustment): array => $adjustment->toArray(), $this->adjustments),
            'delivery_charge' => $this->deliveryCharge()->toDecimal(),
            'grand_total' => $this->grandTotal()->toDecimal(),
            'you_save' => $this->youSave()->toDecimal(),
        ];
    }
}
