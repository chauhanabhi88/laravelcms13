<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Illuminate\Support\Facades\DB;

/**
 * Display names of attribute options ("M", "Red") in the current locale, falling back to
 * the default locale and then the option code; one query per call.
 */
class OptionLabels
{
    /**
     * @param  list<int>  $optionIds
     * @return array<int, string> option id => name
     */
    public function for(array $optionIds): array
    {
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));

        if ($optionIds === []) {
            return [];
        }

        $locale = app()->getLocale();
        $fallback = (string) config('translatable.fallback_locale', config('app.fallback_locale', 'en'));

        return DB::table('attribute_option as o')
            ->leftJoin('attribute_option_translation as t', fn ($join) => $join->on('t.attribute_option_id', '=', 'o.id')->where('t.locale', $locale))
            ->leftJoin('attribute_option_translation as f', fn ($join) => $join->on('f.attribute_option_id', '=', 'o.id')->where('f.locale', $fallback))
            ->whereIn('o.id', $optionIds)
            ->get(['o.id', 'o.custom_option', 't.name as name', 'f.name as fallback_name'])
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => (string) ($row->name ?? $row->fallback_name ?? $row->custom_option ?? $row->id)])
            ->all();
    }

    /**
     * "M / Red" for a variant's options, in attribute id order.
     *
     * @param  array<int, int>  $options  attribute id => option id
     * @param  array<int, string>  $labels  from for()
     */
    public static function combination(array $options, array $labels): string
    {
        ksort($options);

        return implode(' / ', array_map(fn (int $optionId): string => $labels[$optionId] ?? (string) $optionId, array_values($options)));
    }
}
