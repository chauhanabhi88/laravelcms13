<?php

declare(strict_types=1);

namespace Modules\Order\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\SellerOrder;

/**
 * Seller quality metrics (FR-FUL-008, plan M14 §5.8): one seller_quality_metric row per seller
 * and day with the day's orders, seller cancellations, late dispatches (shipped after the
 * deadline, or cancelled by the SLA job), return requests and RTOs. Re-running a day replaces
 * its rows. The supplier dashboard turns the last 30 days into rates.
 */
class SellerQualityService
{
    /**
     * @return int sellers written
     */
    public function snapshot(Carbon $date): int
    {
        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();
        $rows = [];

        $add = function (iterable $counts, string $column) use (&$rows): void {
            foreach ($counts as $sellerId => $count) {
                $rows[(int) $sellerId][$column] = ($rows[(int) $sellerId][$column] ?? 0) + (int) $count;
            }
        };

        // Orders that reached the seller (not those cancelled while waiting for payment).
        $add($this->countBySeller(SellerOrder::query()
            ->whereBetween('created_at', [$from, $to])
            ->where('status', '!=', SellerOrder::STATUS_PENDING_PAYMENT)
            ->where(fn ($query) => $query->where('status', '!=', SellerOrder::STATUS_CANCELLED)
                ->orWhere('cancelled_by', '!=', OrderCancellationService::BY_SYSTEM)
                ->orWhereColumn('cancelled_at', '>', 'sla_dispatch_by'))), 'orders');

        $add($this->countBySeller(SellerOrder::query()
            ->whereBetween('cancelled_at', [$from, $to])
            ->where('cancelled_by', OrderCancellationService::BY_SELLER)), 'cancelled_by_seller');

        $add($this->countBySeller(SellerOrder::query()
            ->whereBetween('shipped_at', [$from, $to])
            ->whereColumn('shipped_at', '>', 'sla_dispatch_by')), 'late_dispatch');

        $add($this->countBySeller(SellerOrder::query()
            ->whereBetween('cancelled_at', [$from, $to])
            ->where('cancelled_by', OrderCancellationService::BY_SYSTEM)
            ->whereColumn('cancelled_at', '>', 'sla_dispatch_by')), 'late_dispatch');

        $add($this->historyBySeller(SellerOrder::STATUS_RETURN_REQUESTED, $from, $to), 'returns');
        $add($this->historyBySeller(SellerOrder::STATUS_RTO_INITIATED, $from, $to), 'rto');

        if ($rows === []) {
            return 0;
        }

        $ratings = DB::table('seller')->whereIn('id', array_keys($rows))->where('rating_count', '>', 0)->pluck('rating_avg', 'id');
        $now = Carbon::now();

        $records = array_map(fn (int $sellerId, array $counts): array => [
            'seller_id' => $sellerId,
            'date' => $from->toDateString(),
            'orders' => $counts['orders'] ?? 0,
            'cancelled_by_seller' => $counts['cancelled_by_seller'] ?? 0,
            'late_dispatch' => $counts['late_dispatch'] ?? 0,
            'returns' => $counts['returns'] ?? 0,
            'rto' => $counts['rto'] ?? 0,
            'rating_avg' => $ratings[$sellerId] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($rows), array_values($rows));

        DB::table('seller_quality_metric')->upsert(
            $records,
            ['seller_id', 'date'],
            ['orders', 'cancelled_by_seller', 'late_dispatch', 'returns', 'rto', 'rating_avg', 'updated_at'],
        );

        return count($records);
    }

    /**
     * @param  Builder<SellerOrder>  $query
     * @return array<int, int>
     */
    protected function countBySeller(Builder $query): array
    {
        return $query->toBase()
            ->select('seller_id', DB::raw('count(*) as total'))
            ->groupBy('seller_id')
            ->pluck('total', 'seller_id')
            ->all();
    }

    /**
     * Seller orders that entered $status in the period, per seller (from the status history).
     *
     * @return array<int, int>
     */
    protected function historyBySeller(string $status, Carbon $from, Carbon $to): array
    {
        return DB::table('status_history')
            ->join('seller_order', 'seller_order.id', '=', 'status_history.model_id')
            ->where('status_history.model_type', (new SellerOrder)->getMorphClass())
            ->where('status_history.to_status', $status)
            ->whereBetween('status_history.created_at', [$from, $to])
            ->select('seller_order.seller_id', DB::raw('count(distinct seller_order.id) as total'))
            ->groupBy('seller_order.seller_id')
            ->pluck('total', 'seller_id')
            ->all();
    }
}
