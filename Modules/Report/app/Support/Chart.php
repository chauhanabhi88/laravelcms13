<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Carbon\CarbonImmutable;

/**
 * Chart payloads for report.js (Chart.js 2, from the Theme vendor bundle). Series colours come
 * from the validated palette by fixed slot, so a series keeps its colour whatever else is
 * shown; the comparison period is the muted "previous" colour. Every chart is also rendered as
 * a table (partials/chart).
 *
 * @phpstan-type Series array{label: string, data: list<float|int|null>, color: string, previous?: bool}
 * @phpstan-type Payload array{id: string, type: string, format: string, labels: list<string>, series: list<Series>, stacked: bool}
 */
final class Chart
{
    /**
     * @param  list<string>  $labels
     * @param  list<Series>  $series
     * @return Payload
     */
    public static function make(string $id, string $type, string $format, array $labels, array $series, bool $stacked = false): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'format' => $format,
            'labels' => array_values($labels),
            'series' => array_values($series),
            'stacked' => $stacked,
        ];
    }

    /**
     * @param  list<float|int|null>  $data
     * @return Series
     */
    public static function series(string $label, array $data, int $slot = 0): array
    {
        $palette = (array) config('report.palette');

        return ['label' => $label, 'data' => array_values($data), 'color' => (string) ($palette[$slot % max(1, count($palette))] ?? '#0d9488')];
    }

    /**
     * The comparison period's series, aligned with the current one by position.
     *
     * @param  list<float|int|null>  $data
     * @return Series
     */
    public static function previous(string $label, array $data): array
    {
        return ['label' => $label, 'data' => array_values($data), 'color' => (string) config('report.palette_previous', '#94a3b8'), 'previous' => true];
    }

    /**
     * Axis labels of a list of Y-m-d dates.
     *
     * @param  list<string>  $dates
     * @return list<string>
     */
    public static function dayLabels(array $dates): array
    {
        return array_map(fn (string $date): string => CarbonImmutable::parse($date)->format('d M'), $dates);
    }

    /**
     * A metric of Metrics::derive() for each day.
     *
     * @param  array<string, array<string, int>>  $daily
     * @return list<float|int|null>
     */
    public static function daily(array $daily, string $metric): array
    {
        return array_values(array_map(fn (array $sums) => Metrics::number(Metrics::derive($sums)[$metric]), $daily));
    }

    /**
     * Top rows of a ranked list with the rest folded into "Other" (no ninth colour).
     *
     * @param  list<array{label: string, value: float|int}>  $rows
     * @return list<array{label: string, value: float|int}>
     */
    public static function foldOther(array $rows, int $keep, string $otherLabel): array
    {
        if (count($rows) <= $keep + 1) {
            return $rows;
        }

        $other = array_sum(array_column(array_slice($rows, $keep), 'value'));

        return [...array_slice($rows, 0, $keep), ['label' => $otherLabel, 'value' => $other]];
    }
}
