<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\SellerOrder;

/**
 * Read-only base queries over the order tables, shared by the aggregators and the live
 * leaderboards. Every query leaves out:
 * - seller orders cancelled before confirmation (they never left pending_payment: failed or
 *   expired prepaid payments, expired COD OTPs); a confirmed seller order has a status history
 *   row to pending_acceptance;
 * - zero-value exchange seller orders (return_request.exchange_seller_order_id).
 *
 * Money expressions are integer paise.
 */
class OrderFacts
{
    /**
     * Line value after the coupon share, in paise.
     */
    public const LINE_PAISE = 'ROUND((i.price * i.quantity - i.discount_allocated) * 100)';

    /**
     * Items of orders placed in [from, to], one row per item with its order, seller order,
     * shipping state and category path. Joins: i (item), o (order), so (seller order),
     * a (shipping address), c (leaf category).
     */
    public function placedItems(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->items()->whereBetween('o.placed_at', [$from, $to]);
    }

    /**
     * Items of seller orders shipped in [from, to].
     */
    public function shippedItems(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->items()->whereBetween('so.shipped_at', [$from, $to]);
    }

    /**
     * Items of seller orders delivered in [from, to].
     */
    public function deliveredItems(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->items()->whereBetween('so.delivered_at', [$from, $to]);
    }

    /**
     * The common joins and exclusions.
     */
    public function items(): Builder
    {
        $sellerOrderType = (new SellerOrder)->getMorphClass();

        return DB::table('sales_order_item as i')
            ->join('sales_order as o', 'o.id', '=', 'i.sales_order_id')
            ->join('seller_order as so', 'so.id', '=', 'i.seller_order_id')
            ->leftJoin('sales_order_address as a', function ($join): void {
                $join->on('a.sales_order_id', '=', 'o.id')->where('a.type', '=', 'shipping');
            })
            ->leftJoin('product as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('catalog_category as c', 'c.id', '=', 'p.catalog_category_id')
            ->whereNotNull('o.placed_at')
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('return_request as rr')
                    ->whereColumn('rr.exchange_seller_order_id', 'so.id');
            })
            ->where(function (Builder $query) use ($sellerOrderType): void {
                $query->where('so.status', '!=', SellerOrder::STATUS_CANCELLED)
                    ->orWhereExists(function (Builder $confirmed) use ($sellerOrderType): void {
                        $confirmed->select(DB::raw(1))
                            ->from('status_history as h')
                            ->where('h.model_type', $sellerOrderType)
                            ->whereColumn('h.model_id', 'so.id')
                            ->where('h.to_status', SellerOrder::STATUS_PENDING_ACCEPTANCE);
                    });
            });
    }
}
