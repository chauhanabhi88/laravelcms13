<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Core\Support\Money;

/**
 * One row the adjustment pipeline adds to the price summary: a charge (delivery, COD fee) is
 * positive, a discount (coupon, automatic offer, M18) negative.
 *
 * A discount may carry $lineShares (cart item id => its positive share, summing to −amount)
 * so place-order stores each line's share on the order item; without them the discount is
 * spread over every line by value. $meta holds what the step's owner needs later (e.g. the
 * coupon id and who funds it).
 */
final readonly class TotalsAdjustment
{
    public const DELIVERY = 'delivery';

    public const COD_FEE = 'cod_fee';

    public const COUPON = 'coupon';

    public const OFFER = 'offer';

    /**
     * Codes whose (negative) amounts are order discounts allocated to the items.
     */
    public const DISCOUNT_CODES = [self::COUPON, self::OFFER];

    /**
     * @param  array<int, Money>  $lineShares
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $code,
        public string $label,
        public Money $amount,
        public array $lineShares = [],
        public array $meta = [],
    ) {}

    public function isDiscount(): bool
    {
        return $this->amount->isNegative();
    }

    /**
     * "FREE" for a zero charge, "−₹50" for a discount, "₹40" otherwise.
     */
    public function displayAmount(): string
    {
        if ($this->amount->isZero()) {
            return trans('cart::front.summary.free');
        }

        return $this->isDiscount() ? '−'.Money::paise(-$this->amount->paise)->format() : $this->amount->format();
    }

    /**
     * @return array{code: string, label: string, amount: string, display: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'amount' => $this->amount->toDecimal(),
            'display' => $this->displayAmount(),
        ];
    }
}
