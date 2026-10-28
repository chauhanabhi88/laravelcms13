<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

/**
 * Masked destinations for the delivery log: enough to tell recipients apart, not to reuse.
 */
final class Mask
{
    /**
     * +919876543210 → ********3210
     */
    public static function phone(?string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return '';
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    /**
     * meera.k@example.com → me*****@example.com
     */
    public static function email(?string $email): string
    {
        $email = trim((string) $email);

        if (! str_contains($email, '@')) {
            return $email === '' ? '' : self::phone($email);
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
    }

    /**
     * A push token's last eight characters.
     */
    public static function token(?string $token): string
    {
        $token = (string) $token;

        return $token === '' ? '' : '…'.substr($token, -8);
    }
}
