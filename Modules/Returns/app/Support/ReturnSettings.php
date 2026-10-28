<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Modules\Core\Support\Money;

/**
 * Typed access to admin > Settings > Returns with config('returns.defaults') fallbacks, so
 * callers never read settings() without a default.
 */
final class ReturnSettings
{
    public static function autoApprove(): bool
    {
        return self::flag('auto_approve');
    }

    /**
     * Highest return rate (percent of delivered packages in the look-back window) still
     * approved automatically / treated as trusted.
     */
    public static function autoApproveMaxReturnRate(): int
    {
        return min(100, self::integer('auto_approve_max_return_rate'));
    }

    /**
     * Returns worth more than this go to a CX agent.
     */
    public static function autoApproveMaxValue(): Money
    {
        $value = self::get('auto_approve_max_value');

        return Money::of(is_numeric($value) && (float) $value >= 0 ? (string) $value : (string) config('returns.defaults.auto_approve_max_value', 2000));
    }

    public static function refundOnPickupForTrusted(): bool
    {
        return self::flag('refund_on_pickup_for_trusted');
    }

    public static function trustedMinDeliveredOrders(): int
    {
        return max(1, self::integer('trusted_min_delivered_orders'));
    }

    public static function maxImages(): int
    {
        return max(1, min(10, self::integer('max_images')));
    }

    public static function claimWindowHours(): int
    {
        return max(1, self::integer('claim_window_hours'));
    }

    public static function refundDeliveryFeeOnSellerFault(): bool
    {
        return self::flag('refund_delivery_fee_on_seller_fault');
    }

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('returns.defaults.'.$key)) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('returns.defaults.'.$key)));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('returns', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
