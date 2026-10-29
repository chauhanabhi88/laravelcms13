<?php

declare(strict_types=1);

namespace Modules\Promotion\Support;

use Modules\Cart\DTO\Totals;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Core\Support\Money;
use Modules\Promotion\DTO\CouponEvaluation;
use Modules\Promotion\Services\DiscountAllocator;

/**
 * Turns a valid CouponEvaluation into price-summary rows. All promotions together never take
 * more than Settings > Promotions > max_coupon_discount_percent_of_cart of the subtotal: a
 * discount above what is left is cut down and re-allocated. A free-delivery coupon replaces
 * the delivery row with a zero one.
 *
 * The row's meta carries coupon_id, code, name, funded_by, stackable and delivery_waived for
 * place-order (OrderPromotions) and the coupon box.
 */
class PromotionTotals
{
    public function __construct(private readonly DiscountAllocator $allocator) {}

    public function add(Totals $totals, string $code, string $label, CouponEvaluation $evaluation): Totals
    {
        $coupon = $evaluation->coupon;
        $discount = $evaluation->discount ?? Money::zero();
        $shares = $evaluation->lineShares;
        $room = $this->room($totals, $code);

        if ($discount->compare($room) > 0) {
            $discount = $room;
            $shares = $this->allocator->allocate($discount, $evaluation->lineShares);
        }

        $waived = $coupon->isFreeDelivery() ? $totals->deliveryCharge() : Money::zero();

        if (! $discount->isPositive() && ! $waived->isPositive()) {
            return $totals;
        }

        if ($waived->isPositive()) {
            $delivery = $totals->adjustment(TotalsAdjustment::DELIVERY);
            $totals = $totals->withAdjustment(new TotalsAdjustment(TotalsAdjustment::DELIVERY, $delivery->label ?? trans('cart::front.summary.delivery'), Money::zero()));
        }

        return $totals->withAdjustment(new TotalsAdjustment(
            $code,
            $label,
            Money::paise(-$discount->paise),
            $discount->isPositive() ? $shares : [],
            [
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
                'funded_by' => $coupon->funded_by,
                'stackable' => $coupon->isStackable(),
                'type' => $coupon->type,
                'delivery_waived' => $waived->toDecimal(),
            ],
        ));
    }

    /**
     * What promotions may still take off: the cap minus the other promotion rows.
     */
    protected function room(Totals $totals, string $exceptCode): Money
    {
        $cap = $totals->subtotal->percent(PromotionSettings::maxDiscountPercent(), \RoundingMode::TowardsZero);

        foreach ($totals->adjustments as $adjustment) {
            if ($adjustment->code !== $exceptCode && in_array($adjustment->code, TotalsAdjustment::DISCOUNT_CODES, true) && $adjustment->amount->isNegative()) {
                $cap = $cap->plus($adjustment->amount);
            }
        }

        return $cap->isNegative() ? Money::zero() : $cap;
    }

    /**
     * The delivery charge a promotion row waived (free-delivery coupons).
     */
    public static function deliveryWaived(TotalsAdjustment $adjustment): Money
    {
        return Money::of((string) ($adjustment->meta['delivery_waived'] ?? '0'));
    }
}
