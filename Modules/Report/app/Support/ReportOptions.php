<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Illuminate\Support\Facades\DB;

/**
 * Options of the report filter bar.
 */
final class ReportOptions
{
    /**
     * L1 (root) categories.
     *
     * @return array<int, string>
     */
    public static function categories(): array
    {
        $ids = DB::table('catalog_category')->whereNull('parent_id')->whereNull('deleted_at')->orderBy('sort_order')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $names = ReportLabels::categories($ids);

        return array_combine($ids, array_map(fn (int $id): string => ReportLabels::name($names, $id), $ids)) ?: [];
    }

    /**
     * Sellers by store name (at most report.seller_filter_limit).
     *
     * @return array<int, string>
     */
    public static function sellers(): array
    {
        $ids = DB::table('seller')->orderByRaw('COALESCE(store_name, legal_name, code)')->limit((int) config('report.seller_filter_limit', 500))->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $names = ReportLabels::sellers($ids);

        return array_combine($ids, array_map(fn (int $id): string => ReportLabels::name($names, $id), $ids)) ?: [];
    }

    /**
     * Directory states and any state that has report data, by name.
     *
     * @return array<int, string>
     */
    public static function states(): array
    {
        $ids = DB::table('directory_state')->pluck('id')
            ->merge(DB::table('daily_sales_stat')->where('state_id', '>', 0)->distinct()->pluck('state_id'))
            ->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $names = ReportLabels::states($ids);
        $options = array_combine($ids, array_map(fn (int $id): string => ReportLabels::name($names, $id), $ids)) ?: [];
        asort($options);

        return $options;
    }
}
