<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Illuminate\Support\Facades\DB;

/**
 * Display names for report rows, looked up in batches (one query per kind). A name missing in
 * the current locale falls back to any locale, then to "#id".
 */
final class ReportLabels
{
    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function categories(array $ids): array
    {
        return self::translated('catalog_category_translation', 'catalog_category_id', 'name', $ids);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function products(array $ids): array
    {
        return self::translated('product_translation', 'product_id', 'name', $ids);
    }

    /**
     * Store name, else legal name, else the seller code.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function sellers(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('seller')->whereIn('id', $ids)->get(['id', 'store_name', 'legal_name', 'code'])
            ->mapWithKeys(fn (object $seller): array => [(int) $seller->id => (string) ($seller->store_name ?: ($seller->legal_name ?: $seller->code))])
            ->all();
    }

    /**
     * Directory state names, else the name snapshot on order addresses.
     *
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public static function states(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $names = self::translated('directory_state_translation', 'directory_country_state_id', 'name', $ids);
        $missing = array_values(array_diff($ids, array_keys($names)));

        if ($missing !== []) {
            $names += DB::table('sales_order_address')->whereIn('state_id', $missing)->whereNotNull('state_name')
                ->groupBy('state_id')->get(['state_id', DB::raw('MAX(state_name) as state_name')])
                ->mapWithKeys(fn (object $row): array => [(int) $row->state_id => (string) $row->state_name])
                ->all();
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $names
     */
    public static function name(array $names, int $id): string
    {
        return $names[$id] ?? '#'.$id;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private static function translated(string $table, string $foreignKey, string $column, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return [];
        }

        $locale = app()->getLocale();
        $names = [];

        $rows = DB::table($table)->whereIn($foreignKey, $ids)->get([$foreignKey, 'locale', $column]);

        foreach ($rows as $row) {
            $id = (int) $row->{$foreignKey};

            if ($row->{$column} === null || $row->{$column} === '') {
                continue;
            }

            if ($row->locale === $locale || ! isset($names[$id])) {
                $names[$id] = (string) $row->{$column};
            }
        }

        return $names;
    }
}
