<?php

declare(strict_types=1);

namespace Modules\Promotion\Adjustments;

use Modules\Cart\Contracts\CartAdjustment;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Promotion\Services\CouponService;
use Modules\Promotion\Services\CouponValidator;
use Modules\Promotion\Support\PromotionContextResolver;
use Modules\Promotion\Support\PromotionTotals;

/**
 * The applied code coupon (cart.coupon_code) in the price summary, re-validated against the cart
 * every time: a coupon that stopped applying (cart below the minimum, expired, used up) simply
 * adds nothing, and the coupon box says why. Runs after delivery and the COD fee.
 */
class CouponAdjustment implements CartAdjustment
{
    public function __construct(
        private readonly CouponService $coupons,
        private readonly CouponValidator $validator,
        private readonly PromotionContextResolver $contexts,
        private readonly PromotionTotals $promotionTotals,
    ) {}

    public function apply(CartSnapshot $snapshot, Totals $totals, ?string $paymentMethod = null): Totals
    {
        if ($snapshot->couponCode === null || $snapshot->couponCode === '') {
            return $totals;
        }

        $coupon = $this->coupons->findByCode($snapshot->couponCode);

        if ($coupon === null) {
            return $totals;
        }

        $evaluation = $this->validator->evaluate($coupon, $snapshot, $totals, $this->contexts->for($snapshot, $paymentMethod));

        if (! $evaluation->valid) {
            return $totals;
        }

        return $this->promotionTotals->add($totals, TotalsAdjustment::COUPON, trans('promotion::front.summary.coupon', ['code' => $coupon->code]), $evaluation);
    }
}
