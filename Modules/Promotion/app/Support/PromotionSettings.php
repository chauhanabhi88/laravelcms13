<?php

declare(strict_types=1);

namespace Modules\Promotion\Support;

use Modules\Core\Support\Money;

/**
 * Typed access to admin > Settings > Promotions with config('promotion.defaults') fallbacks,
 * so callers never read settings() without a default.
 */
final class PromotionSettings
{
    /**
     * A code coupon and an automatic offer may both apply when both are stackable.
     */
    public static function allowStacking(): bool
    {
        return self::flag('allow_stacking');
    }

    /**
     * The most all promotions together may take off the cart subtotal, in percent.
     */
    public static function maxDiscountPercent(): int
    {
        return max(0, min(100, self::integer('max_coupon_discount_percent_of_cart')));
    }

    /**
     * First-order coupons cannot be used with Cash on Delivery (abuse through new accounts).
     */
    public static function firstOrderPrepaidOnly(): bool
    {
        return self::flag('first_order_prepaid_only');
    }

    public static function referralEnabled(): bool
    {
        return self::flag('referral_enabled');
    }

    public static function referralRewardAmount(): Money
    {
        return Money::of(max(0, self::integer('referral_reward_amount')));
    }

    public static function referralRewardMinCart(): Money
    {
        return Money::of(max(0, self::integer('referral_reward_min_cart')));
    }

    public static function referralRewardValidityDays(): int
    {
        return max(1, min(365, self::integer('referral_reward_validity_days')));
    }

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('promotion.defaults.'.$key)) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('promotion.defaults.'.$key));
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('promotion', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
