<?php

declare(strict_types=1);

namespace Modules\Storefront\Support;

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

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('storefront.defaults.'.$key)) === (int) config('core.yes');
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('storefront', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
