<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

/**
 * Typed access to the Seller settings (admin > Settings > Seller), with config defaults,
 * so callers never read settings() without a fallback.
 */
class SellerSettings
{
    public static function bankVerifier(): string
    {
        return self::choice('bank_verifier', (array) config('seller.bank_verifiers'));
    }

    public static function gstVerifier(): string
    {
        return self::choice('gst_verifier', (array) config('seller.gst_verifiers'));
    }

    public static function gstVerifierUrl(): string
    {
        return trim((string) (self::get('gst_verifier_url') ?: config('seller.defaults.gst_verifier_url')));
    }

    public static function gstVerifierKey(): string
    {
        return (string) (self::get('gst_verifier_key') ?: config('seller.defaults.gst_verifier_key'));
    }

    public static function allowsNonGstSellers(): bool
    {
        $value = self::get('allow_non_gst_sellers');

        return (int) (is_numeric($value) ? $value : config('seller.defaults.allow_non_gst_sellers')) === (int) config('core.yes');
    }

    /**
     * Minimum penny-drop name similarity (0-100) for a bank account to verify automatically.
     */
    public static function nameMatchThreshold(): int
    {
        $value = self::get('name_match_threshold');
        $threshold = (int) (is_numeric($value) ? $value : config('seller.defaults.name_match_threshold'));

        return max(0, min(100, $threshold));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    protected static function choice(string $key, array $allowed): string
    {
        $value = (string) self::get($key);

        return in_array($value, $allowed, true) ? $value : (string) config('seller.defaults.'.$key);
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('seller', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
