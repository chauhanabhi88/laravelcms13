<?php

declare(strict_types=1);

namespace Modules\Order\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;

/**
 * A delivered seller order adds its units to each product's sold_count (sorting and badges).
 */
class UpdateProductSoldCounts implements ShouldQueue
{
    public function handle(SellerOrderStatusChanged $event): void
    {
        if ($event->to !== SellerOrder::STATUS_DELIVERED) {
            return;
        }

        $units = SalesOrderItem::query()
            ->where('seller_order_id', $event->sellerOrder->id)
            ->where('status', '!=', SalesOrderItem::STATUS_CANCELLED)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as units')
            ->pluck('units', 'product_id');

        foreach ($units as $productId => $count) {
            DB::table('product')->where('id', $productId)->increment('sold_count', (int) $count);
        }
    }
}
