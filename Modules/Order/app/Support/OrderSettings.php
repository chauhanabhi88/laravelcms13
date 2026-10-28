<?php

declare(strict_types=1);

namespace Modules\Order\Support;

use Modules\Order\Models\LabelBatch;

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

    /**
     * Platform default label paper (a seller may choose their own): a4_4up | thermal_4x6.
     */
    public static function labelFormat(): string
    {
        $value = (string) self::get('label_format');

        return in_array($value, LabelBatch::FORMATS, true) ? $value : (string) config('order.defaults.label_format', LabelBatch::FORMAT_A4);
    }

    /**
     * Hours past the dispatch deadline before an order not yet picked up is cancelled.
     */
    public static function slaGraceHours(): int
    {
        return self::integer('sla_grace_hours');
    }

    /**
     * Days after an RTO reaches the seller before it is confirmed as received in good condition.
     */
    public static function autoConfirmReturnDays(): int
    {
        return max(1, self::integer('auto_confirm_return_days'));
    }

    /**
     * Reseller orders show the reseller (not the supplier) as the sender on the packing slip.
     */
    public static function resellerWhiteLabel(): bool
    {
        return self::flag('reseller_white_label');
    }

    /**
     * Our tax invoice goes into the label batch even when the courier label carries one.
     */
    public static function includeOwnInvoice(): bool
    {
        return self::flag('include_own_invoice');
    }

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('order.defaults.'.$key)) === (int) config('core.yes');
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
