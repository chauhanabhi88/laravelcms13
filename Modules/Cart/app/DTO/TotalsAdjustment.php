<?php

declare(strict_types=1);

namespace Modules\Cart\DTO;

use Modules\Core\Support\Money;

/**
 * One row the adjustment pipeline adds to the price summary: a charge (delivery, COD fee) is
 * positive, a discount (coupon, M18) negative.
 */
final readonly class TotalsAdjustment
{
    public const DELIVERY = 'delivery';

    public const COD_FEE = 'cod_fee';

    public const COUPON = 'coupon';

    public function __construct(
        public string $code,
        public string $label,
        public Money $amount,
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
