<?php

declare(strict_types=1);

namespace Modules\Customer\Support;

use Modules\Core\Contracts\ProvidesAppConfig;

/**
 * The "customer" section of GET /api/v1/app/config: which sign-in methods are open and the
 * address / wishlist limits.
 */
final class CustomerAppConfig implements ProvidesAppConfig
{
    public function appConfigKey(): string
    {
        return 'customer';
    }

    public function appConfig(): array
    {
        return [
            'login' => [
                'phone_otp' => true,
                'email_otp' => CustomerSettings::emailOtpEnabled(),
                'password' => CustomerSettings::passwordLoginEnabled(),
            ],
            'otp' => [
                'length' => (int) config('core.otp.length', 6),
                'expires_in' => (int) config('core.otp.expires_minutes', 5) * 60,
                'resend_in' => (int) config('core.otp.resend_cooldown_seconds', 30),
            ],
            'max_addresses' => CustomerSettings::maxAddresses(),
            'max_wishlist_items' => CustomerSettings::maxWishlistItems(),
        ];
    }
}
