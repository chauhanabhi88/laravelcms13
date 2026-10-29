<?php

declare(strict_types=1);

namespace Modules\Promotion\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Promotion\Models\Coupon;
use Modules\Promotion\Models\CouponUsage;
use Modules\Promotion\Models\FlashDealItem;
use Throwable;

/**
 * Cancellations give promotions back (plan M18 §5): the flash-deal units of cancelled items
 * return to their deal, and once the whole order is cancelled its coupon usages are reversed,
 * so the buyer (and the coupon's total limit) can use them again. A cancellation never fails
 * because of this listener (errors are reported).
 */
class ReverseCouponUsageOnCancel
{
    public function handle(OrderCancelled $event): void
    {
        try {
            DB::transaction(function () use ($event): void {
                $this->releaseDealUnits($event->itemIds);

                $status = SalesOrder::query()->whereKey($event->order->id)->value('status');

                if ($status === SalesOrder::STATUS_CANCELLED) {
                    $this->reverseUsages((int) $event->order->id);
                }
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<int>  $itemIds
     */
    protected function releaseDealUnits(array $itemIds): void
    {
        if ($itemIds === []) {
            return;
        }

        // An item is cancelled once (OrderCancellationService), so its units go back once.
        $items = SalesOrderItem::query()
            ->whereIn('id', $itemIds)
            ->where('status', SalesOrderItem::STATUS_CANCELLED)
            ->whereNotNull('flash_deal_item_id')
            ->get(['id', 'flash_deal_item_id', 'quantity', 'cancelled_qty']);

        foreach ($items as $item) {
            $units = $item->cancelled_qty > 0 ? $item->cancelled_qty : $item->quantity;

            FlashDealItem::query()->whereKey($item->flash_deal_item_id)->update(['sold' => DB::raw('GREATEST(CAST(sold AS SIGNED) - '.(int) $units.', 0)')]);
        }
    }

    protected function reverseUsages(int $orderId): void
    {
        $usages = CouponUsage::query()->applied()->where('sales_order_id', $orderId)->lockForUpdate()->get();

        foreach ($usages as $usage) {
            $usage->forceFill(['status' => CouponUsage::STATUS_REVERSED, 'reversed_at' => now()])->save();
            Coupon::query()->whereKey($usage->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
        }
    }
}
