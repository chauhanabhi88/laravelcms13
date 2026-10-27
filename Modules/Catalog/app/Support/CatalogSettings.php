<?php

declare(strict_types=1);

namespace Modules\Catalog\Support;

/**
 * Typed access to the Catalog settings (admin > Settings > Catalog), with config
 * defaults, so callers never read settings() without a fallback.
 */
class CatalogSettings
{
    public static function defaultReturnWindow(): int
    {
        return self::integer('default_return_window');
    }

    public static function defaultDispatchSlaHours(): int
    {
        return self::integer('default_dispatch_sla_hours');
    }

    public static function defaultGstPercent(): string
    {
        $value = self::get('default_gst_percent');

        return number_format((float) (is_numeric($value) ? $value : config('catalog.defaults.default_gst_percent')), 2, '.', '');
    }

    /**
     * Number of tree levels an admin may build (roots are level 1).
     */
    public static function maxCategoryDepth(): int
    {
        return max(1, self::integer('max_category_depth'));
    }

    public static function maxVariantAxes(): int
    {
        return max(1, self::integer('max_variant_axes'));
    }

    public static function defaultReturnType(): string
    {
        return (string) config('catalog.defaults.return_type', 'return_exchange');
    }

    /**
     * Locales a translatable catalog record is written in: the enabled languages,
     * or the app locale when the Language table is empty.
     *
     * @return list<string>
     */
    public static function contentLocales(): array
    {
        $locales = array_keys((array) getLanguageOptions());

        return $locales !== [] ? array_values(array_map('strval', $locales)) : [(string) config('app.locale', 'en')];
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('catalog.defaults.'.$key));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('catalog', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
