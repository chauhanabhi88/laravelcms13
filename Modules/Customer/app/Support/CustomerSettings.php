<?php

declare(strict_types=1);

namespace Modules\Customer\Support;

/**
 * Typed access to the "customer" settings group (Settings > Customer) with config fallbacks.
 * 1/2 settings left blank count as enabled.
 */
final class CustomerSettings
{
    public static function emailOtpEnabled(): bool
    {
        return self::flag('enable_email_otp');
    }

    public static function passwordLoginEnabled(): bool
    {
        return self::flag('enable_password_login');
    }

    public static function maxAddresses(): int
    {
        return max(1, (int) config('customer.max_addresses', 20));
    }

    public static function maxWishlistItems(): int
    {
        return max(1, (int) config('customer.max_wishlist_items', 500));
    }

    public static function deletionGraceDays(): int
    {
        return max(0, (int) config('customer.deletion_grace_days', 7));
    }

    private static function flag(string $key): bool
    {
        $value = settings('customer', $key);

        return $value === null || $value === '' || (int) $value !== (int) config('core.no');
    }
}
