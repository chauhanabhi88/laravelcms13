<?php

declare(strict_types=1);

namespace Modules\Product\Support;

/**
 * Typed access to the Product settings (admin > Settings > Product), with config defaults,
 * so callers never read settings() without a fallback.
 */
class ProductSettings
{
    /**
     * Submissions from trusted sellers go live without manual QC.
     */
    public static function autoApproveTrustedSellers(): bool
    {
        $value = self::get('auto_approve_trusted_sellers');

        return (int) (is_numeric($value) ? $value : config('product.defaults.auto_approve_trusted_sellers')) === (int) config('core.yes');
    }

    /**
     * Minimum seller rating (with at least one rating) to count as trusted, e.g. "4.30".
     */
    public static function trustedSellerMinRating(): string
    {
        $value = self::get('trusted_seller_min_rating');
        $rating = is_numeric($value) ? (float) $value : (float) config('product.defaults.trusted_seller_min_rating');

        return number_format(max(0, min(5, $rating)), 2, '.', '');
    }

    /**
     * Hours a submission may wait in the QC queue before it shows as overdue.
     */
    public static function qcSlaHours(): int
    {
        $value = self::get('qc_sla_hours');

        return max(1, (int) (is_numeric($value) ? $value : config('product.defaults.qc_sla_hours')));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('product', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
