<?php

declare(strict_types=1);

namespace Modules\Logistics\Support;

use Modules\Logistics\Models\CourierRule;

/**
 * Typed access to admin > Settings > Logistics with config('logistics.defaults') fallbacks, so
 * callers never read settings() without a default.
 */
final class LogisticsSettings
{
    /**
     * Code of the courier used when no rule matches (plan M13 §5.2 step 3).
     */
    public static function defaultCourier(): string
    {
        $value = self::get('default_courier');

        return is_string($value) && trim($value) !== '' ? trim($value) : (string) config('logistics.defaults.default_courier', 'shiprocket');
    }

    public static function defaultStrategy(): string
    {
        $value = (string) (self::get('default_strategy') ?? '');

        return in_array($value, (array) config('logistics.strategies'), true) && $value !== CourierRule::STRATEGY_PREFERRED_LIST
            ? $value
            : (string) config('logistics.defaults.default_strategy', CourierRule::STRATEGY_RECOMMENDED);
    }

    /**
     * Whether the PDP/checkout estimate asks the courier API (else the pincode matrix only).
     */
    public static function liveServiceability(): bool
    {
        $value = self::get('live_serviceability');

        return (int) (is_numeric($value) ? $value : config('logistics.defaults.live_serviceability')) === (int) config('core.yes');
    }

    /**
     * Failed attempts after which an NDR goes back to origin automatically (0 = never).
     */
    public static function autoRtoAfterAttempts(): int
    {
        return self::integer('auto_rto_after_attempts');
    }

    public static function pollIntervalHours(): int
    {
        return max(1, self::integer('poll_interval_hours'));
    }

    public static function serviceabilityCacheHours(): int
    {
        return max(1, self::integer('serviceability_cache_hours'));
    }

    public static function estimatorTimeoutSeconds(): float
    {
        return max(200, self::integer('estimator_timeout_ms')) / 1000;
    }

    /**
     * RTO freight as a percentage of the forward freight (courier policy; M17 charges it).
     */
    public static function rtoChargePercent(): int
    {
        return self::integer('rto_charge_percent');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('logistics.defaults.'.$key)));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('logistics', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
