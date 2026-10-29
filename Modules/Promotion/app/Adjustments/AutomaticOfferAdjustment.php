<?php

declare(strict_types=1);

namespace Modules\Promotion\Adjustments;

use Modules\Cart\Contracts\CartAdjustment;
use Modules\Cart\DTO\CartSnapshot;
use Modules\Cart\DTO\Totals;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Promotion\DTO\CouponEvaluation;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Services\CouponValidator;
use Modules\Promotion\Support\PromotionContextResolver;
use Modules\Promotion\Support\PromotionSettings;
use Modules\Promotion\Support\PromotionTotals;

/**
 * Automatic offers (FR-PRM-002): live coupons with is_auto = 1 apply without a code; the one
 * saving the buyer most wins (at most one per cart). With a code coupon applied, an offer is
 * added only when Settings > Promotions > allow_stacking is on and both are stackable.
 * Runs after CouponAdjustment.
 */
class AutomaticOfferAdjustment implements CartAdjustment
{
    public function __construct(
        private readonly CouponValidator $validator,
        private readonly PromotionContextResolver $contexts,
        private readonly PromotionTotals $promotionTotals,
    ) {}

    public function apply(CartSnapshot $snapshot, Totals $totals, ?string $paymentMethod = null): Totals
    {
        $coupon = $totals->adjustment(TotalsAdjustment::COUPON);

        if ($coupon !== null && (! PromotionSettings::allowStacking() || ! ($coupon->meta['stackable'] ?? false))) {
            return $totals;
        }

        $offers = Coupon::query()->live()->automatic()->orderBy('id')->get();

        if ($offers->isEmpty()) {
            return $totals;
        }

        $context = $this->contexts->for($snapshot, $paymentMethod);
        $best = null;

        foreach ($offers as $offer) {
            if ($coupon !== null && (! $offer->isStackable() || (int) ($coupon->meta['coupon_id'] ?? 0) === $offer->id)) {
                continue;
            }

            $evaluation = $this->validator->evaluate($offer, $snapshot, $totals, $context);

            if ($evaluation->valid && ($best === null || $evaluation->benefit()->compare($best->benefit()) > 0)) {
                $best = $evaluation;
            }
        }

        if (! $best instanceof CouponEvaluation || ! $best->benefit()->isPositive()) {
            return $totals;
        }

        return $this->promotionTotals->add($totals, TotalsAdjustment::OFFER, trans('promotion::front.summary.offer', ['name' => $best->coupon->name]), $best);
    }
}
