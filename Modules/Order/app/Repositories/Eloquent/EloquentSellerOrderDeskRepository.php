<?php

declare(strict_types=1);

namespace Modules\Order\Repositories\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Modules\Core\Repositories\Eloquent\EloquentBaseRepository;
use Modules\Order\Models\SellerOrder;
use Modules\Order\Models\SellerQualityMetric;
use Modules\Order\Repositories\SellerOrderDeskRepository;
use Modules\Payment\Models\Payment;
use Modules\Seller\Models\Seller;

class EloquentSellerOrderDeskRepository extends EloquentBaseRepository implements SellerOrderDeskRepository
{
    public function orders(int $sellerId, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($sellerId, $filters)
            ->with(['salesOrder:id,order_no,payment_method,placed_at,is_reseller_order,created_at', 'items'])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function tabCounts(int $sellerId): array
    {
        return Cache::remember(self::countsKey($sellerId), (int) config('order.fulfilment.counts_ttl', 60), function () use ($sellerId): array {
            $byStatus = SellerOrder::query()
                ->forSeller($sellerId)
                ->toBase()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($total): int => (int) $total)
                ->all();

            $counts = [];

            foreach (self::TABS as $tab => $statuses) {
                $counts[$tab] = array_sum(array_intersect_key($byStatus, array_flip($statuses)));
            }

            return $counts;
        });
    }

    public function forgetCounts(int $sellerId): void
    {
        Cache::forget(self::countsKey($sellerId));
    }

    public function export(int $sellerId, array $filters): LazyCollection
    {
        // lazyById pages by id, so the desk's SLA ordering has to go.
        return $this->query($sellerId, $filters)
            ->reorder()
            ->with(['salesOrder:id,order_no,payment_method,placed_at,is_reseller_order,created_at', 'items'])
            ->lazyById(200, 'seller_order.id', 'id');
    }

    public function ownedByIds(int $sellerId, array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return new Collection;
        }

        return SellerOrder::query()->forSeller($sellerId)->whereKey($ids)->orderBy('id')->get()->toBase();
    }

    public function findBySubOrderNo(int $sellerId, string $subOrderNo): ?SellerOrder
    {
        return SellerOrder::query()->forSeller($sellerId)->where('sub_order_no', $subOrderNo)->first();
    }

    public function dashboardCounts(int $sellerId): array
    {
        $counts = $this->tabCounts($sellerId);
        $statusCount = fn (array $statuses): int => SellerOrder::query()->forSeller($sellerId)->whereIn('status', $statuses)->count();

        return [
            'orders_today' => SellerOrder::query()->forSeller($sellerId)
                ->where('status', '!=', SellerOrder::STATUS_PENDING_PAYMENT)
                ->where('created_at', '>=', Carbon::today())
                ->count(),
            'pending_acceptance' => $counts[self::TAB_PENDING] ?? 0,
            'ready_to_ship' => $statusCount([SellerOrder::STATUS_READY_TO_SHIP]),
            'returns' => $statusCount([SellerOrder::STATUS_RETURN_REQUESTED, SellerOrder::STATUS_RTO_INITIATED])
                + SellerOrder::query()->forSeller($sellerId)->where('status', SellerOrder::STATUS_RTO_DELIVERED)->whereNull('receipt_confirmed_at')->count(),
            'sla_breached' => SellerOrder::query()->forSeller($sellerId)->slaBreached()->count(),
        ];
    }

    public function qualitySummary(int $sellerId, int $days = 30): array
    {
        $totals = SellerQualityMetric::query()
            ->forSeller($sellerId)
            ->where('date', '>=', Carbon::today()->subDays($days)->toDateString())
            ->toBase()
            ->selectRaw('coalesce(sum(orders), 0) as orders, coalesce(sum(cancelled_by_seller), 0) as cancelled, coalesce(sum(late_dispatch), 0) as late, coalesce(sum(returns), 0) as returns, coalesce(sum(rto), 0) as rto')
            ->first();

        $orders = (int) ($totals->orders ?? 0);
        $rate = fn (mixed $count): ?float => $orders > 0 ? round(((int) $count) * 100 / $orders, 1) : null;
        $seller = Seller::query()->find($sellerId, ['id', 'rating_avg', 'rating_count']);

        return [
            'orders' => $orders,
            'cancellation_rate' => $rate($totals->cancelled ?? 0),
            'late_dispatch_rate' => $rate($totals->late ?? 0),
            'return_rate' => $rate($totals->returns ?? 0),
            'rto_rate' => $rate($totals->rto ?? 0),
            'rating' => $seller !== null && $seller->rating_count > 0 ? (float) $seller->rating_avg : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<SellerOrder>
     */
    protected function query(int $sellerId, array $filters): Builder
    {
        $tab = array_key_exists((string) ($filters['tab'] ?? ''), self::TABS) ? (string) $filters['tab'] : self::TAB_PENDING;
        $query = SellerOrder::query()->forSeller($sellerId)->whereIn('seller_order.status', self::TABS[$tab]);

        if ($tab === self::TAB_READY) {
            match ($filters['sub'] ?? null) {
                self::SUB_LABEL_PENDING => $query->where('seller_order.status', SellerOrder::STATUS_READY_TO_SHIP)->whereNull('seller_order.label_generated_at'),
                self::SUB_AWAITING_PICKUP => $query->where('seller_order.status', SellerOrder::STATUS_PACKED),
                default => null,
            };
        }

        if (($search = trim((string) ($filters['q'] ?? ''))) !== '') {
            $query->where(fn (Builder $inner) => $inner
                ->where('seller_order.sub_order_no', 'like', $search.'%')
                ->orWhere('seller_order.invoice_no', $search)
                ->orWhereIn('seller_order.sales_order_id', fn ($sub) => $sub->select('id')->from('sales_order')->where('order_no', $search)));
        }

        if (($sku = trim((string) ($filters['sku'] ?? ''))) !== '') {
            $query->whereIn('seller_order.id', fn ($sub) => $sub->select('seller_order_id')->from('sales_order_item')
                ->where(fn ($match) => $match->where('seller_sku', 'like', $sku.'%')->orWhere('sku', 'like', $sku.'%')));
        }

        if (in_array($filters['payment'] ?? null, ['cod', 'prepaid'], true)) {
            $query->whereIn('seller_order.sales_order_id', fn ($sub) => $sub->select('id')->from('sales_order')
                ->where('payment_method', $filters['payment'] === 'cod' ? '=' : '!=', Payment::METHOD_COD));
        }

        if (($from = $this->date($filters['from'] ?? null)) !== null) {
            $query->where('seller_order.created_at', '>=', $from->startOfDay());
        }

        if (($to = $this->date($filters['to'] ?? null)) !== null) {
            $query->where('seller_order.created_at', '<=', $to->endOfDay());
        }

        $now = Carbon::now();
        $atRisk = $now->copy()->addHours((int) config('order.fulfilment.sla_at_risk_hours', 12));

        // Only orders still with the seller have a dispatch clock.
        if (in_array($tab, [self::TAB_PENDING, self::TAB_READY], true)) {
            match ($filters['sla'] ?? null) {
                self::SLA_BREACHED => $query->slaBreached($now),
                self::SLA_AT_RISK => $query->whereBetween('seller_order.sla_dispatch_by', [$now, $atRisk]),
                self::SLA_ON_TIME => $query->where('seller_order.sla_dispatch_by', '>', $atRisk),
                default => null,
            };
        }

        return in_array($tab, [self::TAB_PENDING, self::TAB_READY], true)
            ? $query->orderBy('seller_order.sla_dispatch_by')->orderBy('seller_order.id')
            : $query->orderByDesc('seller_order.id');
    }

    protected function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function countsKey(int $sellerId): string
    {
        return 'order:desk_counts:'.$sellerId;
    }
}
