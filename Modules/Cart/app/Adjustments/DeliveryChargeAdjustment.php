<?php

declare(strict_types=1);

namespace Modules\Cart\Adjustments;

use Modules\Cart\Contracts\CartAdjustment;
use Modules\Cart\DTO\CartGroup;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Cart\Support\CartSettings;
use Modules\Core\Support\Money;

/**
 * Delivery charge per Settings > Cart > free_delivery (always shown, "FREE" when zero):
 * - always            nothing to pay (the default, value-commerce norm)
 * - threshold         delivery_fee once per order below free_delivery_min
 * - per_seller_group  delivery_fee per seller package below free_delivery_min (0 = every package)
 */
class DeliveryChargeAdjustment implements CartAdjustment
{
    public function apply(CartSnapshot $snapshot, Totals $totals, ?string $paymentMethod = null): Totals
    {
        return $totals->withAdjustment(new TotalsAdjustment(
            TotalsAdjustment::DELIVERY,
            trans('cart::front.summary.delivery'),
            $this->charge($snapshot, $totals),
        ));
    }

    protected function charge(CartSnapshot $snapshot, Totals $totals): Money
    {
        $fee = CartSettings::deliveryFee();
        $minimum = CartSettings::freeDeliveryMinimum();

        return match (CartSettings::freeDeliveryMode()) {
            CartSettings::DELIVERY_THRESHOLD => $fee->isPositive() && $totals->subtotal->compare($minimum) < 0 ? $fee : Money::zero(),
            CartSettings::DELIVERY_PER_SELLER_GROUP => $fee->times(count(array_filter(
                $snapshot->groups,
                fn (CartGroup $group): bool => ! $minimum->isPositive() || $group->subtotal()->compare($minimum) < 0,
            ))),
            default => Money::zero(),
        };
    }
}
