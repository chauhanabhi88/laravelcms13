<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

use Modules\Core\Support\Money;

/**
 * Typed access to the Storefront settings (admin > Settings > Storefront), with config
 * defaults, so callers never read settings() without a fallback.
 */
class StorefrontSettings
{
    /**
     * Out-of-stock products stay listed (sorted last) instead of being hidden.
     */
    public static function showOutOfStock(): bool
    {
        return self::flag('show_out_of_stock');
    }

    /**
     * Product cards carry the "Free Delivery" chip.
     */
    public static function freeDeliveryBadge(): bool
    {
        return self::flag('free_delivery_badge');
    }

    /**
     * Admin-picked trending terms, shown before the computed ones.
     *
     * @return list<string>
     */
    public static function manualTrendingTerms(): array
    {
        $value = self::get('trending_terms_manual');
        $value = is_string($value) && $value !== '' ? $value : (string) config('storefront.defaults.trending_terms_manual');

        return array_values(array_unique(array_filter(array_map(
            fn (string $term): string => mb_strtolower(trim($term)),
            explode(',', $value),
        ), fn (string $term): bool => $term !== '')));
    }

    /**
     * Transit days per warehouse → destination relationship (pincode-matrix delivery estimate).
     * The setting is JSON; missing or invalid buckets keep their config default.
     *
     * @return array{same_city: int, same_state: int, metro_metro: int, roi: int, special: int}
     */
    public static function transitDays(): array
    {
        $defaults = array_map('intval', (array) config('storefront.defaults.transit_days'));
        $value = self::get('transit_days');
        $configured = is_string($value) && $value !== '' ? json_decode($value, true) : (is_array($value) ? $value : null);

        foreach (is_array($configured) ? $configured : [] as $bucket => $days) {
            if (array_key_exists((string) $bucket, $defaults) && is_numeric($days) && (int) $days >= 0) {
                $defaults[(string) $bucket] = (int) $days;
            }
        }

        /** @var array{same_city: int, same_state: int, metro_metro: int, roi: int, special: int} $defaults */
        return $defaults;
    }

    /**
     * City ids treated as metros besides pincodes whose zone is "metro".
     *
     * @return list<int>
     */
    public static function metroCityIds(): array
    {
        return self::idList('metro_city_ids');
    }

    /**
     * State ids treated as special delivery zones (NE, J&K, Ladakh, islands) besides
     * pincodes whose zone is "ne_jk" or "special".
     *
     * @return list<int>
     */
    public static function specialZoneStateIds(): array
    {
        return self::idList('special_zone_state_ids');
    }

    /**
     * The product page shows "Only N left" at or below this many available units.
     */
    public static function lowStockDisplayThreshold(): int
    {
        return self::integer('low_stock_display_threshold');
    }

    /**
     * Similar products cost within ± this percentage of the product's price.
     */
    public static function similarPriceBandPercent(): int
    {
        return self::integer('similar_price_band_percent');
    }

    /**
     * Highest order value eligible for Cash on Delivery: Settings > Payment (M11), else an
     * Order module setting, else the storefront default.
     */
    public static function codMaxValue(): Money
    {
        $value = self::read('payment', 'cod_max_value');
        $value = is_numeric($value) ? $value : self::read('order', 'cod_max_value');

        return Money::of(is_numeric($value) ? (string) $value : (string) config('storefront.defaults.cod_max_value'));
    }

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('storefront.defaults.'.$key)) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('storefront.defaults.'.$key)));
    }

    /**
     * @return list<int>
     */
    protected static function idList(string $key): array
    {
        $value = self::get($key);
        $value = is_string($value) && trim($value) !== '' ? $value : (string) config('storefront.defaults.'.$key);

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $value)), fn (int $id): bool => $id > 0)));
    }

    protected static function get(string $key): mixed
    {
        return self::read('storefront', $key);
    }

    protected static function read(string $module, string $key): mixed
    {
        try {
            return settings($module, $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
