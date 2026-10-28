<?php

declare(strict_types=1);

namespace Modules\Order\Support;

/**
 * Typed access to admin > Settings > Order with config('order.defaults') fallbacks, so callers
 * never read settings() without a default.
 */
final class OrderSettings
{
    /**
     * RTOs within the window that block COD for a customer (0 = never block).
     */
    public static function rtoBlockThreshold(): int
    {
        return self::integer('rto_block_threshold');
    }

    public static function rtoWindowDays(): int
    {
        return max(1, self::integer('rto_window_days'));
    }

    /**
     * Undelivered COD orders a customer may have open at once (0 = no limit).
     */
    public static function maxOpenCodOrders(): int
    {
        return self::integer('max_open_cod_orders');
    }

    /**
     * A reseller's price for their customer may be at most MRP × this (decimal string, ≥ 1).
     */
    public static function resellerMaxMarkupMultiplier(): string
    {
        $value = self::get('reseller_max_markup_multiplier');
        $value = is_numeric($value) ? (string) $value : (string) config('order.defaults.reseller_max_markup_multiplier', '1.5');

        return (float) $value >= 1 ? $value : '1';
    }

    public static function allowCustomerCancelAfterPacked(): bool
    {
        $value = self::get('allow_customer_cancel_after_packed');

        return (int) (is_numeric($value) ? $value : config('order.defaults.allow_customer_cancel_after_packed')) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('order.defaults.'.$key)));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('order', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
