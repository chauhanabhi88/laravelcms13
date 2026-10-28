<?php

declare(strict_types=1);

namespace Modules\Cart\Contracts;

use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;

/**
 * One step of the price-summary pipeline (CartTotals). Built-ins add the delivery charge and
 * the COD fee; the Promotion module (M18) adds its coupon step. Register a step by tagging it
 * with CartTotals::ADJUSTMENT_TAG; steps run in registration order.
 */
interface CartAdjustment
{
    /**
     * @param  string|null  $paymentMethod  "cod", "prepaid"... once checkout knows it, else null
     */
    public function apply(CartSnapshot $snapshot, Totals $totals, ?string $paymentMethod = null): Totals;
}
