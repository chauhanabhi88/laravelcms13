<?php

declare(strict_types=1);

namespace Modules\Promotion\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Promotion\Models\CouponUsage;

/**
 * Aggregates of the promotion report (read only, plain arrays). Cancelled orders and reversed
 * usages are left out.
 */
class PromotionReportService
{
    /**
     * Per coupon / offer used in the range: usages, orders, discount cost, revenue, AOV.
     *
     * @return list<array{coupon_id: int, code: string, name: string, is_auto: bool, funded_by: string, orders: int, customers: int, discount: Money, revenue: Money, aov: Money}>
     */
    public function coupons(Carbon $from, Carbon $to): array
    {
        return DB::table('coupon_usage as u')
            ->join('coupon as c', 'c.id', '=', 'u.coupon_id')
            ->join('sales_order as o', 'o.id', '=', 'u.sales_order_id')
            ->where('u.status', CouponUsage::STATUS_APPLIED)
            ->whereBetween('u.created_at', [$from, $to])
            ->groupBy('u.coupon_id', 'c.code', 'c.name', 'c.is_auto', 'c.funded_by')
            ->orderByDesc(DB::raw('SUM(u.discount_amount)'))
            ->limit(200)
            ->get([
                'u.coupon_id', 'c.code', 'c.name', 'c.is_auto', 'c.funded_by',
                DB::raw('COUNT(DISTINCT u.sales_order_id) as orders'),
                DB::raw('COUNT(DISTINCT u.customer_id) as customers'),
                DB::raw('SUM(u.discount_amount) as discount'),
                DB::raw('SUM(o.grand_total) as revenue'),
            ])
            ->map(function (object $row): array {
                $revenue = Money::of((string) ($row->revenue ?? '0'));
                $orders = (int) $row->orders;

                return [
                    'coupon_id' => (int) $row->coupon_id,
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'is_auto' => (int) $row->is_auto === (int) config('core.yes'),
                    'funded_by' => (string) $row->funded_by,
                    'orders' => $orders,
                    'customers' => (int) $row->customers,
                    'discount' => Money::of((string) ($row->discount ?? '0')),
                    'revenue' => $revenue,
                    'aov' => self::average($revenue, $orders),
                ];
            })
            ->all();
    }

    /**
     * Orders placed in the range with and without a promotion, their AOV and the uplift in
     * percent ((AOV with − AOV without) / AOV without).
     *
     * @return array{with: array{orders: int, revenue: Money, aov: Money}, without: array{orders: int, revenue: Money, aov: Money}, uplift_percent: float|null, discount: Money}
     */
    public function uplift(Carbon $from, Carbon $to): array
    {
        $promoted = DB::table('coupon_usage')->where('status', CouponUsage::STATUS_APPLIED)->select('sales_order_id');
        $base = fn () => DB::table('sales_order')
            ->whereBetween('placed_at', [$from, $to])
            ->where('status', '!=', SalesOrder::STATUS_CANCELLED);

        $with = $base()->whereIn('id', $promoted)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue, COALESCE(SUM(coupon_discount), 0) as discount')->first();
        $without = $base()->whereNotIn('id', $promoted)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total), 0) as revenue')->first();

        $summary = fn (object $row): array => [
            'orders' => (int) $row->orders,
            'revenue' => Money::of((string) $row->revenue),
            'aov' => self::average(Money::of((string) $row->revenue), (int) $row->orders),
        ];
        $withSummary = $summary($with);
        $withoutSummary = $summary($without);

        return [
            'with' => $withSummary,
            'without' => $withoutSummary,
            'uplift_percent' => $withoutSummary['aov']->isPositive() && $withSummary['orders'] > 0
                ? round(($withSummary['aov']->paise - $withoutSummary['aov']->paise) * 100 / $withoutSummary['aov']->paise, 1)
                : null,
            'discount' => Money::of((string) $with->discount),
        ];
    }

    public static function average(Money $total, int $count): Money
    {
        return $count > 0 ? Money::paise(intdiv($total->paise, $count)) : Money::zero();
    }

    /**
     * Units sold and revenue at flash-deal prices per deal (items placed in the range, cancelled
     * items excluded).
     *
     * @return list<array{deal_id: int, name: string, units: int, revenue: Money, starts_at: string|null, ends_at: string|null}>
     */
    public function flashDeals(Carbon $from, Carbon $to): array
    {
        return DB::table('sales_order_item as i')
            ->join('flash_deal_item as fi', 'fi.id', '=', 'i.flash_deal_item_id')
            ->join('flash_deal as d', 'd.id', '=', 'fi.flash_deal_id')
            ->where('i.status', '!=', SalesOrderItem::STATUS_CANCELLED)
            ->whereBetween('i.created_at', [$from, $to])
            ->groupBy('d.id', 'd.name', 'd.starts_at', 'd.ends_at')
            ->orderByDesc('d.id')
            ->limit(100)
            ->get([
                'd.id', 'd.name', 'd.starts_at', 'd.ends_at',
                DB::raw('SUM(i.quantity) as units'),
                DB::raw('SUM(i.price * i.quantity - i.discount_allocated) as revenue'),
            ])
            ->map(fn (object $row): array => [
                'deal_id' => (int) $row->id,
                'name' => (string) $row->name,
                'units' => (int) $row->units,
                'revenue' => Money::of((string) ($row->revenue ?? '0')),
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
            ])
            ->all();
    }
}
