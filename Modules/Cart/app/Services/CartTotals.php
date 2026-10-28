<?php

declare(strict_types=1);

namespace Modules\Cart\Services;

use Illuminate\Contracts\Container\Container;
use Modules\Cart\Contracts\CartAdjustment;
use Modules\Cart\DTO\CartLine;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Core\Support\Money;

/**
 * The price summary of a cart snapshot (FR-CRT-004): total MRP (Σ MRP × qty), discount on
 * MRP (Σ (MRP − price) × qty) and subtotal from the purchasable lines only, then every
 * tagged CartAdjustment in order. Money is exact paise arithmetic throughout.
 */
class CartTotals
{
    public const ADJUSTMENT_TAG = 'cart.adjustments';

    public const PAYMENT_COD = 'cod';

    public function __construct(protected Container $container) {}

    public function calculate(CartSnapshot $snapshot, ?string $paymentMethod = null): Totals
    {
        $totals = $this->base($snapshot);

        if ($totals->itemCount === 0) {
            return $totals;
        }

        foreach ($this->adjustments() as $adjustment) {
            $totals = $adjustment->apply($snapshot, $totals, $paymentMethod);
        }

        return $totals;
    }

    protected function base(CartSnapshot $snapshot): Totals
    {
        $mrp = Money::zero();
        $subtotal = Money::zero();
        $count = 0;

        foreach ($snapshot->lines() as $line) {
            /** @var CartLine $line */
            $mrp = $mrp->plus($line->lineMrp());
            $subtotal = $subtotal->plus($line->lineTotal());
            $count += $line->quantity;
        }

        return new Totals($mrp, $mrp->minus($subtotal), $subtotal, $count);
    }

    /**
     * @return iterable<CartAdjustment>
     */
    protected function adjustments(): iterable
    {
        return $this->container->tagged(self::ADJUSTMENT_TAG);
    }
}
