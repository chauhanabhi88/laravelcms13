<?php

declare(strict_types=1);

namespace Modules\Report\Services;

use Modules\Report\Support\Format;
use Modules\Report\Support\Metrics;
use Modules\Report\Support\ReportFilters;
use Modules\Report\Support\ReportRange;

/**
 * KPI summary of a range (plan M19 §5): values, change against the comparison period and the
 * daily series behind each tile's sparkline.
 */
class KpiService
{
    public function __construct(private readonly ReportQuery $query) {}

    /**
     * @param  list<array{0: string, 1: string, 2: bool|null}>  $tiles  [metric, format, higher is better]
     * @return array{
     *     daily: array<string, array<string, int>>,
     *     previousDaily: array<string, array<string, int>>|null,
     *     current: array<string, mixed>,
     *     previous: array<string, mixed>|null,
     *     tiles: list<array{key: string, format: string, value: mixed, display: string, delta: float|null, delta_display: string|null, trend: string, sparkline: list<float|int|null>}>
     * }
     */
    public function summary(ReportRange $range, ReportFilters $filters, array $tiles = Metrics::KPI_TILES): array
    {
        $daily = $this->query->daily($range, $filters);
        $current = Metrics::derive($this->query->totals($range, $filters, $daily));

        $compareRange = $range->compare();
        $previousDaily = $compareRange !== null ? $this->query->daily($compareRange, $filters) : null;
        $previous = $previousDaily !== null ? Metrics::derive($this->query->totals($compareRange, $filters, $previousDaily)) : null;

        $derivedDays = array_map(fn (array $sums): array => Metrics::derive($sums), $daily);

        return [
            'daily' => $daily,
            'previousDaily' => $previousDaily,
            'current' => $current,
            'previous' => $previous,
            'tiles' => array_map(function (array $tile) use ($current, $previous, $derivedDays): array {
                [$key, $format, $higherIsBetter] = $tile;
                $delta = $previous !== null ? Metrics::delta($current[$key], $previous[$key], $format) : null;

                return [
                    'key' => $key,
                    'format' => $format,
                    'value' => $current[$key],
                    'display' => Format::display($current[$key], $format),
                    'delta' => $delta,
                    'delta_display' => Format::delta($delta, $format),
                    'trend' => $this->trend($delta, $higherIsBetter),
                    'sparkline' => array_values(array_map(fn (array $day) => Metrics::number($day[$key]), $derivedDays)),
                ];
            }, $tiles),
        ];
    }

    /**
     * good / bad / neutral: whether the change is an improvement.
     */
    private function trend(?float $delta, ?bool $higherIsBetter): string
    {
        if ($delta === null || $delta == 0.0 || $higherIsBetter === null) {
            return 'neutral';
        }

        return ($delta > 0) === $higherIsBetter ? 'good' : 'bad';
    }
}
