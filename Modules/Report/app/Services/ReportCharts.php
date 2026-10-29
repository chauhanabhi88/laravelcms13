<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Modules\Report\Support\Chart;
use Modules\Report\Support\Format;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\ReportRange;

/**
 * The report charts (plan M19 §7): GMV current vs previous, seller orders by status, category
 * mix, COD vs prepaid, RTO % by state, plus single-metric trends.
 */
class ReportCharts
{
    public function __construct(private readonly LeaderboardService $leaderboards) {}

    /**
     * A daily metric, with the comparison period aligned by position when there is one.
     *
     * @param  array<string, array<string, int>>  $daily
     * @param  array<string, array<string, int>>|null  $previousDaily
     * @return array<string, mixed>
     */
    public function trend(string $id, string $metric, string $format, array $daily, ?array $previousDaily = null): array
    {
        $series = [Chart::series(trans('report::report.charts.current_period'), Chart::daily($daily, $metric))];

        if ($previousDaily !== null) {
            $series[] = Chart::previous(trans('report::report.charts.previous_period'), Chart::daily($previousDaily, $metric));
        }

        return Chart::make($id, 'line', $format, Chart::dayLabels(array_keys($daily)), $series);
    }

    /**
     * Seller orders placed each day, stacked by where they are now.
     *
     * @param  array<string, array<string, int>>  $daily
     * @return array<string, mixed>
     */
    public function statusMix(array $daily): array
    {
        $buckets = ['status_delivered', 'status_rto', 'status_open', 'status_returned', 'cancelled_orders'];
        $series = [];

        foreach ($buckets as $slot => $bucket) {
            $series[] = Chart::series(trans('report::report.charts.status.'.$bucket), array_values(array_column($daily, $bucket)), $slot);
        }

        return Chart::make('chart-status', 'bar', Format::COUNT, Chart::dayLabels(array_keys($daily)), $series, true);
    }

    /**
     * COD and prepaid orders per day.
     *
     * @param  array<string, array<string, int>>  $daily
     * @return array<string, mixed>
     */
    public function paymentTrend(array $daily): array
    {
        return Chart::make('chart-payments', 'line', Format::COUNT, Chart::dayLabels(array_keys($daily)), [
            Chart::series(trans('report::report.columns.prepaid_orders'), Chart::daily($daily, 'prepaid_orders'), 0),
            Chart::series(trans('report::report.columns.cod_orders'), Chart::daily($daily, 'cod_orders'), 1),
        ]);
    }

    /**
     * GMV per L1 category, largest first (top 8 + Other).
     *
     * @return array<string, mixed>
     */
    public function categoryMix(ReportRange $range): array
    {
        $rows = array_map(fn (array $row): array => ['label' => $row['name'], 'value' => Metrics::number($row['gmv'])], $this->leaderboards->categories($range));
        $rows = Chart::foldOther($rows, 8, trans('report::report.charts.other'));

        return Chart::make('chart-categories', 'horizontalBar', Format::MONEY, array_column($rows, 'label'), [
            Chart::series(trans('report::report.columns.gmv'), array_column($rows, 'value')),
        ]);
    }

    /**
     * RTO rate of the states with the most returns to origin (sorted, highest first).
     *
     * @return array<string, mixed>
     */
    public function rtoByState(ReportRange $range, int $limit = 10): array
    {
        $rows = $this->leaderboards->states($range, 'rto', $limit);

        return Chart::make('chart-rto-states', 'horizontalBar', Format::PERCENT, array_column($rows, 'name'), [
            Chart::series(trans('report::report.columns.rto_rate'), array_column($rows, 'rto_rate'), 1),
        ]);
    }

    /**
     * A ranked list as a single-series horizontal bar.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function ranked(string $id, array $rows, string $labelKey, string $valueKey, string $format, string $seriesLabel, int $slot = 0): array
    {
        return Chart::make($id, 'horizontalBar', $format, array_map(fn (array $row): string => (string) $row[$labelKey], $rows), [
            Chart::series($seriesLabel, array_map(fn (array $row) => Metrics::number($row[$valueKey]), $rows), $slot),
        ]);
    }
}
