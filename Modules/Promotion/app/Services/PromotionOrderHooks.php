<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Support\Carbon;
use Modules\Cart\DTO\TotalsAdjustment;
use Modules\Core\Support\Money;
use Modules\Order\Contracts\OrderPromotions;
use Modules\Order\DTO\CheckoutSummary;
use Modules\Order\Exceptions\CheckoutException;
use Modules\Order\Exceptions\PriceChanged;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Promotion\Models\FlashDealItem;
use Modules\Promotion\Support\PromotionTotals;

/**
 * Promotions inside the place-order transaction (plan M18 §5 "Concurrency"). After the variant
 * rows are locked and the order is written:
 *
 * - every coupon / offer row of the summary locks its coupon row, re-checks the total and
 *   per-customer limits against the applied usages and records a usage (so two buyers racing for
 *   the last use of a coupon cannot both get it);
 * - every item sold at a flash-deal price locks its deal row and takes its units from the cap
 *   (none left: the price fell back, PriceChanged).
 *
 * Any failure throws and rolls the order back.
 */
class PromotionOrderHooks implements OrderPromotions
{
    public function __construct(private readonly FlashDealPricing $flashDeals) {}

    public function onPlacing(SalesOrder $order, CheckoutSummary $summary): void
    {
        $this->recordCoupons($order, $summary);
        $this->claimFlashDealUnits($order);
    }

    /**
     * @throws CheckoutException
     */
    protected function recordCoupons(SalesOrder $order, CheckoutSummary $summary): void
    {
        $rows = array_filter(
            $summary->totals->adjustments,
            fn (TotalsAdjustment $row): bool => in_array($row->code, TotalsAdjustment::DISCOUNT_CODES, true) && isset($row->meta['coupon_id']),
        );
        usort($rows, fn (TotalsAdjustment $a, TotalsAdjustment $b): int => (int) $a->meta['coupon_id'] <=> (int) $b->meta['coupon_id']);

        foreach ($rows as $row) {
            $coupon = Coupon::query()->whereKey((int) $row->meta['coupon_id'])->lockForUpdate()->first();
            $code = (string) ($row->meta['code'] ?? '');

            if ($coupon === null || ! $coupon->isEnabled() || ($coupon->ends_at !== null && $coupon->ends_at->lessThanOrEqualTo(Carbon::now()))) {
                throw new CheckoutException(trans('promotion::front.errors.expired', ['code' => $code]), 'coupon_unavailable', CheckoutException::STEP_SUMMARY);
            }

            $applied = CouponUsage::query()->applied()->where('coupon_id', $coupon->id);

            if ($coupon->usage_limit_total !== null && (clone $applied)->count() >= $coupon->usage_limit_total) {
                throw new CheckoutException(trans('promotion::front.errors.exhausted', ['code' => $coupon->code]), 'coupon_limit_reached', CheckoutException::STEP_SUMMARY);
            }

            if ($coupon->usage_limit_per_customer !== null && (clone $applied)->where('customer_id', $order->customer_id)->count() >= $coupon->usage_limit_per_customer) {
                throw new CheckoutException(trans('promotion::front.errors.already_used', ['code' => $coupon->code]), 'coupon_already_used', CheckoutException::STEP_SUMMARY);
            }

            CouponUsage::query()->create([
                'coupon_id' => $coupon->id,
                'customer_id' => $order->customer_id,
                'sales_order_id' => $order->id,
                'code' => $coupon->code,
                'discount_amount' => Money::paise(-$row->amount->paise)->plus(PromotionTotals::deliveryWaived($row)),
                'status' => CouponUsage::STATUS_APPLIED,
            ]);
            Coupon::query()->whereKey($coupon->id)->increment('used_count');
        }
    }

    /**
     * @throws PriceChanged
     */
    protected function claimFlashDealUnits(SalesOrder $order): void
    {
        $items = SalesOrderItem::query()->where('sales_order_id', $order->id)->orderBy('id')->get(['id', 'variant_id', 'price', 'quantity']);
        $deals = $this->flashDeals->lockRunning($items->pluck('variant_id')->unique()->values()->all());

        if ($deals->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            $deal = $deals
                ->filter(fn (FlashDealItem $deal): bool => $deal->variant_id === $item->variant_id && $deal->deal_price->equals($item->price))
                ->sortBy('id')
                ->first();

            if (! $deal instanceof FlashDealItem) {
                continue;
            }

            if ($deal->stock_cap !== null && $deal->stock_cap - $deal->sold < $item->quantity) {
                throw new PriceChanged;
            }

            $deal->sold += $item->quantity;
            FlashDealItem::query()->whereKey($deal->id)->increment('sold', $item->quantity);
            $item->forceFill(['flash_deal_item_id' => $deal->id])->save();
        }
    }
}
