<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Order\Models\SellerOrder;
use Modules\Report\Support\Grain;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\ReportLabels;
use Modules\Report\Support\ReportRange;

/**
 * Leaderboards (plan M19 §5, FR-RPT-002): top sellers, products, categories, states and
 * pincodes, each with its RTO and return rates. Rows are plain arrays (money as Money).
 * A null limit returns every row (exports).
 */
class LeaderboardService
{
    public const SELLER_SORTS = ['gmv', 'orders', 'rating'];

    public const PRODUCT_SORTS = ['units', 'gmv'];

    public const PLACE_SORTS = ['gmv', 'rto'];

    public function __construct(
        private readonly ReportQuery $query,
        private readonly OrderFacts $facts,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function sellers(ReportRange $range, string $sort = 'gmv', ?int $limit = null): array
    {
        $members = $this->query->byDimension($range, Grain::DIMENSION_SELLER);
        $ids = array_map('intval', array_keys($members));
        $names = ReportLabels::sellers($ids);
        $ratings = $ids === [] ? [] : DB::table('seller')->whereIn('id', $ids)->pluck('rating_avg', 'id')->all();

        $rows = [];
        foreach ($members as $id => $sums) {
            $metrics = Metrics::derive($sums);
            $rows[] = [
                'seller_id' => (int) $id,
                'name' => ReportLabels::name($names, (int) $id),
                'gmv' => $metrics['gmv'],
                'orders' => $metrics['orders'],
                'units' => $metrics['units'],
                'aov' => $metrics['aov'],
                'rating' => isset($ratings[$id]) && (float) $ratings[$id] > 0 ? round((float) $ratings[$id], 2) : null,
                'rto_rate' => $metrics['rto_rate'],
                'return_rate' => $metrics['return_rate'],
                'cancellation_rate' => $metrics['cancellation_rate'],
            ];
        }

        $sort = in_array($sort, self::SELLER_SORTS, true) ? $sort : 'gmv';
        usort($rows, fn (array $a, array $b): int => match ($sort) {
            'orders' => [$b['orders'], $b['gmv']->paise] <=> [$a['orders'], $a['gmv']->paise],
            'rating' => [$b['rating'] ?? -1, $b['gmv']->paise] <=> [$a['rating'] ?? -1, $a['gmv']->paise],
            default => [$b['gmv']->paise, $b['orders']] <=> [$a['gmv']->paise, $a['orders']],
        });

        return $this->limit($rows, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(ReportRange $range, string $sort = 'units', ?int $limit = null, ?int $sellerId = null): array
    {
        $products = array_filter($this->query->products($range, $sellerId), fn (array $row): bool => $row['units'] > 0 || $row['views'] > 0);
        $sort = in_array($sort, self::PRODUCT_SORTS, true) ? $sort : 'units';

        uasort($products, fn (array $a, array $b): int => $sort === 'gmv'
            ? [$b['gmv'], $b['units']] <=> [$a['gmv'], $a['units']]
            : [$b['units'], $b['gmv']] <=> [$a['units'], $a['gmv']]);

        $products = $limit !== null ? array_slice($products, 0, $limit, true) : $products;
        $names = ReportLabels::products(array_keys($products));
        $sellers = ReportLabels::sellers(array_values(array_unique(array_column($products, 'seller_id'))));

        $rows = [];
        foreach ($products as $id => $row) {
            $rows[] = [
                'product_id' => $id,
                'name' => ReportLabels::name($names, $id),
                'seller' => ReportLabels::name($sellers, $row['seller_id']),
                'units' => $row['units'],
                'gmv' => Money::paise($row['gmv']),
                'views' => $row['views'],
                'returns' => $row['returns'],
                'return_rate' => Metrics::rate($row['returns'], $row['units']),
            ];
        }

        return $rows;
    }

    /**
     * L1 categories by GMV with their share of the platform GMV.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(ReportRange $range, ?int $limit = null): array
    {
        $members = $this->query->byDimension($range, Grain::DIMENSION_CATEGORY);
        $names = ReportLabels::categories(array_map('intval', array_keys($members)));
        $platformGmv = array_sum(array_column($this->query->byDimension($range, Grain::DIMENSION_PAYMENT), 'gmv'));

        $rows = [];
        foreach ($members as $id => $sums) {
            $metrics = Metrics::derive($sums);
            $rows[] = [
                'category_id' => (int) $id,
                'name' => ReportLabels::name($names, (int) $id),
                'gmv' => $metrics['gmv'],
                'share' => Metrics::rate($sums['gmv'], $platformGmv),
                'orders' => $metrics['orders'],
                'units' => $metrics['units'],
                'return_rate' => $metrics['return_rate'],
                'rto_rate' => $metrics['rto_rate'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['gmv']->paise <=> $a['gmv']->paise);

        return $this->limit($rows, $limit);
    }

    /**
     * Shipping states by GMV, or by RTO rate (fraud hotspots: only states with enough
     * shipments, report.rto_rank_min_shipped).
     *
     * @return list<array<string, mixed>>
     */
    public function states(ReportRange $range, string $sort = 'gmv', ?int $limit = null): array
    {
        $members = $this->query->byDimension($range, Grain::DIMENSION_STATE);
        $names = ReportLabels::states(array_map('intval', array_keys($members)));

        $rows = [];
        foreach ($members as $id => $sums) {
            $metrics = Metrics::derive($sums);
            $rows[] = [
                'state_id' => (int) $id,
                'name' => ReportLabels::name($names, (int) $id),
                'gmv' => $metrics['gmv'],
                'orders' => $metrics['orders'],
                'shipped' => $metrics['shipped_orders'],
                'rto' => $metrics['rto_orders'],
                'rto_rate' => $metrics['rto_rate'],
                'return_rate' => $metrics['return_rate'],
            ];
        }

        return $this->limit($this->sortPlaces($rows, $sort), $limit);
    }

    /**
     * Delivery pincodes by GMV or RTO rate, computed live from the order tables for the range
     * (no pincode aggregate) and cached for a few minutes.
     *
     * @return list<array<string, mixed>>
     */
    public function pincodes(ReportRange $range, string $sort = 'gmv', ?int $limit = null): array
    {
        $key = 'report.pincodes.'.md5(json_encode([$range->from->toDateString(), $range->to->toDateString(), $sort, $limit]));
        $seconds = (int) config('report.live_cache_seconds', 300);
        $build = fn (): array => $this->buildPincodes($range, $sort, $limit);

        return $seconds > 0 ? Cache::remember($key, $seconds, $build) : $build();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPincodes(ReportRange $range, string $sort, ?int $limit): array
    {
        $from = $range->from->startOfDay();
        $to = $range->to->endOfDay();

        $sales = $this->facts->placedItems($from, $to)
            ->whereNotNull('a.pincode')
            ->groupBy('a.pincode')
            ->get(['a.pincode', DB::raw('COUNT(DISTINCT o.id) as orders'), DB::raw('SUM('.OrderFacts::LINE_PAISE.') as gmv_paise'), DB::raw('MAX(a.state_name) as state_name')])
            ->keyBy('pincode');

        $shipping = $this->facts->shippedItems($from, $to)
            ->whereNotNull('a.pincode')
            ->groupBy('a.pincode')
            ->selectRaw('a.pincode, COUNT(DISTINCT so.id) as shipped, COUNT(DISTINCT CASE WHEN so.status IN (?, ?) THEN so.id END) as rto, MAX(a.state_name) as state_name', [SellerOrder::STATUS_RTO_INITIATED, SellerOrder::STATUS_RTO_DELIVERED])
            ->get()
            ->keyBy('pincode');

        $rows = [];
        foreach ($sales->keys()->merge($shipping->keys())->unique() as $pincode) {
            $sale = $sales->get($pincode);
            $ship = $shipping->get($pincode);

            $rows[] = [
                'pincode' => (string) $pincode,
                'state' => (string) ($sale->state_name ?? $ship->state_name ?? ''),
                // Money (Modules\Core\Support) is allowed by Core's CacheClassPolicy.
                'gmv' => Money::paise((int) ($sale->gmv_paise ?? 0)),
                'orders' => (int) ($sale->orders ?? 0),
                'shipped' => (int) ($ship->shipped ?? 0),
                'rto' => (int) ($ship->rto ?? 0),
                'rto_rate' => Metrics::rate((int) ($ship->rto ?? 0), (int) ($ship->shipped ?? 0)),
            ];
        }

        return $this->limit($this->sortPlaces($rows, $sort), $limit);
    }

    /**
     * @param  list<array<string, mixed>>  $rows  with gmv (Money), shipped, rto_rate
     * @return list<array<string, mixed>>
     */
    private function sortPlaces(array $rows, string $sort): array
    {
        if ($sort === 'rto') {
            $minimum = max(1, (int) config('report.rto_rank_min_shipped', 5));
            $rows = array_values(array_filter($rows, fn (array $row): bool => $row['shipped'] >= $minimum && $row['rto'] > 0));
            usort($rows, fn (array $a, array $b): int => [$b['rto_rate'], $b['rto']] <=> [$a['rto_rate'], $a['rto']]);

            return $rows;
        }

        usort($rows, fn (array $a, array $b): int => [$b['gmv']->paise, $b['orders']] <=> [$a['gmv']->paise, $a['orders']]);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function limit(array $rows, ?int $limit): array
    {
        return array_values($limit !== null ? array_slice($rows, 0, $limit) : $rows);
    }
}
