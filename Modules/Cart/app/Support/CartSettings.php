<?php

declare(strict_types=1);

namespace Modules\Cart\Support;

use Modules\Core\Support\Money;

/**
 * Typed access to admin > Settings > Cart with config('cart.defaults') fallbacks, so callers
 * never read settings() without a default.
 */
final class CartSettings
{
    public const DELIVERY_ALWAYS_FREE = 'always';

    public const DELIVERY_THRESHOLD = 'threshold';

    public const DELIVERY_PER_SELLER_GROUP = 'per_seller_group';

    public static function maxQtyPerLine(): int
    {
        return max(1, self::integer('max_qty_per_line'));
    }

    public static function maxLines(): int
    {
        return max(1, self::integer('max_lines'));
    }

    /**
     * always | threshold | per_seller_group (unknown values fall back to the default).
     */
    public static function freeDeliveryMode(): string
    {
        $value = self::get('free_delivery');
        $modes = (array) config('cart.free_delivery_modes', []);

        return is_string($value) && in_array($value, $modes, true) ? $value : (string) config('cart.defaults.free_delivery');
    }

    public static function freeDeliveryMinimum(): Money
    {
        return self::money('free_delivery_min');
    }

    public static function deliveryFee(): Money
    {
        return self::money('delivery_fee');
    }

    public static function codFee(): Money
    {
        return self::money('cod_fee');
    }

    public static function abandonedCartEnabled(): bool
    {
        $value = self::get('abandoned_cart_enabled');

        return (int) (is_numeric($value) ? $value : config('cart.defaults.abandoned_cart_enabled')) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('cart.defaults.'.$key));
    }

    /**
     * A non-negative amount; blank or invalid values use the default.
     */
    protected static function money(string $key): Money
    {
        $value = self::get($key);
        $amount = is_numeric($value) ? Money::of((string) $value) : Money::of((string) config('cart.defaults.'.$key, '0'));

        return $amount->isNegative() ? Money::zero() : $amount;
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('cart', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
