<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Money;
use Modules\Report\Support\Grain;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;

/**
 * Reads the aggregates for a range (plan M19 §5 "KpiService"): stored rows for past days and
 * the live computation for today (the hourly row for today is ignored, so nothing counts
 * twice). Sums are arrays of ints (money in paise), see Metrics.
 */
class ReportQuery
{
    public function __construct(private readonly LiveStats $live) {}

    /**
     * One sums array per day of the range (zero-filled), for the filter's grain.
     *
     * @return array<string, array<string, int>> Y-m-d => sums
     */
    public function daily(ReportRange $range, ReportFilters $filters): array
    {
        $grain = Grain::split($filters->grainKey());
        $days = array_fill_keys($range->dates(), Metrics::zero());

        $stored = $this->stored($range)
            ->where('seller_id', $grain['seller_id'])
            ->where('catalog_category_id', $grain['catalog_category_id'])
            ->where('state_id', $grain['state_id'])
            ->where('payment_method', $grain['payment_method'])
            ->get();

        foreach ($stored as $row) {
            $days[CarbonImmutable::parse($row->date)->toDateString()] = Metrics::normalize($row);
        }

        if ($range->includesToday()) {
            $today = CarbonImmutable::today()->toDateString();
            $live = $this->live->sales()[$filters->grainKey()] ?? null;
            $days[$today] = $live !== null ? Metrics::normalize($live) : Metrics::zero();
        }

        return $days;
    }

    /**
     * @param  array<string, array<string, int>>|null  $daily  reuse a daily() result
     * @return array<string, int>
     */
    public function totals(ReportRange $range, ReportFilters $filters, ?array $daily = null): array
    {
        return array_reduce($daily ?? $this->daily($range, $filters), fn (array $carry, array $day): array => Metrics::add($carry, $day), Metrics::zero());
    }

    /**
     * Sums per member of one dimension (seller id, category id, state id or payment type) over
     * the range.
     *
     * @return array<int|string, array<string, int>>
     */
    public function byDimension(ReportRange $range, string $dimension): array
    {
        ['column' => $column, 'fixed' => $fixed] = Grain::dimension($dimension);

        $query = $this->stored($range)->groupBy($column)->select($column);
        foreach ($fixed as $fixedColumn => $value) {
            $query->where($fixedColumn, $value);
        }
        $column === 'payment_method' ? $query->where($column, '!=', Grain::ALL) : $query->where($column, '>', 0);

        foreach ([...Metrics::COUNTS, ...Metrics::MONEY] as $metric) {
            $query->selectRaw("SUM({$metric}) as {$metric}");
        }

        $members = [];
        foreach ($query->get() as $row) {
            $members[$this->member($row->{$column}, $column)] = Metrics::normalize($row);
        }

        if ($range->includesToday()) {
            foreach ($this->live->sales() as $key => $row) {
                $grain = Grain::split($key);
                $matches = array_filter($fixed, fn ($value, $fixedColumn): bool => $grain[$fixedColumn] !== $value, ARRAY_FILTER_USE_BOTH) === [];
                $member = $grain[$column];

                if (! $matches || $member === 0 || $member === Grain::ALL) {
                    continue;
                }

                $members[$member] = Metrics::add($members[$member] ?? Metrics::zero(), Metrics::normalize($row));
            }
        }

        return $members;
    }

    /**
     * Product sums over the range (live today included), optionally for one seller.
     *
     * @return array<int, array{seller_id: int, units: int, gmv: int, views: int, returns: int}> gmv in paise
     */
    public function products(ReportRange $range, ?int $sellerId = null): array
    {
        $query = DB::table('daily_product_stat')
            ->whereBetween('date', [$range->from->toDateString(), $this->storedUntil($range)])
            ->groupBy('product_id')
            ->select('product_id', DB::raw('MAX(seller_id) as seller_id'), DB::raw('SUM(units) as units'), DB::raw('SUM(gmv) as gmv'), DB::raw('SUM(views) as views'), DB::raw('SUM(returns) as returns'));

        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }

        $products = [];
        foreach ($query->get() as $row) {
            $products[(int) $row->product_id] = [
                'seller_id' => (int) $row->seller_id,
                'units' => (int) $row->units,
                'gmv' => Money::of((string) $row->gmv)->paise,
                'views' => (int) $row->views,
                'returns' => (int) $row->returns,
            ];
        }

        if ($range->includesToday()) {
            // Today's sales are live; today's views come from the hourly snapshot row.
            $todayViews = DB::table('daily_product_stat')->where('date', CarbonImmutable::today()->toDateString())
                ->when($sellerId !== null, fn ($query) => $query->where('seller_id', $sellerId))
                ->where('views', '>', 0)
                ->get(['product_id', 'seller_id', 'views']);

            foreach ($todayViews as $row) {
                $id = (int) $row->product_id;
                $products[$id] ??= ['seller_id' => (int) $row->seller_id, 'units' => 0, 'gmv' => 0, 'views' => 0, 'returns' => 0];
                $products[$id]['views'] += (int) $row->views;
            }

            foreach ($this->live->products() as $id => $row) {
                if ($sellerId !== null && $row['seller_id'] !== $sellerId) {
                    continue;
                }

                $products[$id] ??= ['seller_id' => $row['seller_id'], 'units' => 0, 'gmv' => 0, 'views' => 0, 'returns' => 0];
                $products[$id]['units'] += $row['units'];
                $products[$id]['gmv'] += Money::of($row['gmv'])->paise;
                $products[$id]['returns'] += $row['returns'];
            }
        }

        return $products;
    }

    /**
     * Stored rows from the start of the range up to the day before today (today is live).
     */
    private function stored(ReportRange $range): Builder
    {
        return DB::table('daily_sales_stat')->whereBetween('date', [$range->from->toDateString(), $this->storedUntil($range)]);
    }

    private function storedUntil(ReportRange $range): string
    {
        $yesterday = CarbonImmutable::yesterday();

        return ($range->to->greaterThan($yesterday) ? $yesterday : $range->to)->toDateString();
    }

    private function member(mixed $value, string $column): int|string
    {
        return $column === 'payment_method' ? (string) $value : (int) $value;
    }
}
