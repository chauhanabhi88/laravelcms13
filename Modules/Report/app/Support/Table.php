<?php

declare(strict_types=1);

namespace Modules\Report\Support;

/**
 * A report table: typed columns (labels from report::report.columns.*) and raw rows.
 */
final class Table
{
    /**
     * Delivery pincodes (LeaderboardService::pincodes rows).
     */
    public const PINCODE_COLUMNS = [
        ['pincode', Format::TEXT], ['state', Format::TEXT], ['gmv', Format::MONEY], ['orders', Format::COUNT],
        ['shipped', Format::COUNT], ['rto', Format::COUNT], ['rto_rate', Format::PERCENT],
    ];

    /**
     * @param  list<array{0: string, 1: string}>  $columns  [key, format]
     * @param  list<array<string, mixed>>  $rows
     * @return array{columns: list<array{key: string, label: string, format: string}>, rows: list<array<string, mixed>>}
     */
    public static function make(array $columns, array $rows): array
    {
        return [
            'columns' => array_map(fn (array $column): array => [
                'key' => $column[0],
                'label' => trans('report::report.columns.'.$column[0]),
                'format' => $column[1],
            ], $columns),
            'rows' => array_values($rows),
        ];
    }
}
