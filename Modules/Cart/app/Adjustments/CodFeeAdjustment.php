<?php

declare(strict_types=1);

namespace Modules\Cart\Adjustments;

use Modules\Cart\Contracts\CartAdjustment;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Cart\Services\CartTotals;
use Modules\Cart\Support\CartSettings;

/**
 * Cash on Delivery handling fee: only when the payment method is COD and Settings > Cart >
 * cod_fee is above zero.
 */
class CodFeeAdjustment implements CartAdjustment
{
    public function apply(CartSnapshot $snapshot, Totals $totals, ?string $paymentMethod = null): Totals
    {
        $fee = CartSettings::codFee();

        if ($paymentMethod !== CartTotals::PAYMENT_COD || ! $fee->isPositive()) {
            return $totals;
        }

        return $totals->withAdjustment(new TotalsAdjustment(TotalsAdjustment::COD_FEE, trans('cart::front.summary.cod_fee'), $fee));
    }
}
