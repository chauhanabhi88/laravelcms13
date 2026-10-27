<?php

declare(strict_types=1);

namespace Modules\Customer\Support;

/**
 * Indian mobile numbers for customer accounts, stored in E.164 (+919876543210), the form
 * OTP codes are keyed by. Accepts "9876543210", "+91 98765 43210", "09876543210",
 * "919876543210"; the national part must match ^[6-9]\d{9}$.
 */
final class PhoneNumber
{
    public const PATTERN = '/^[6-9]\d{9}$/';

    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($value, '+') && ! str_starts_with($digits, '91')) {
            return null;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match(self::PATTERN, $digits) === 1 ? '+91'.$digits : null;
    }

    public static function isValid(mixed $value): bool
    {
        return self::normalise($value) !== null;
    }

    /**
     * +919876543210 → 9876543210 (form prefill next to a fixed "+91").
     */
    public static function national(?string $e164): string
    {
        return $e164 !== null && str_starts_with($e164, '+91') ? substr($e164, 3) : (string) $e164;
    }

    /**
     * +919876543210 → +91 98765 43210.
     */
    public static function display(?string $e164): string
    {
        if ($e164 === null || ! preg_match('/^\+91\d{10}$/', $e164)) {
            return (string) $e164;
        }

        return '+91 '.substr($e164, 3, 5).' '.substr($e164, 8);
    }

    /**
     * +919876543210 → +91 98XXX XX210, for "code sent to …" screens.
     */
    public static function mask(?string $e164): string
    {
        if ($e164 === null || ! preg_match('/^\+91\d{10}$/', $e164)) {
            return (string) $e164;
        }

        return '+91 '.substr($e164, 3, 2).'XXX XX'.substr($e164, -3);
    }

    /**
     * ada.lovelace@example.com → ad***@example.com.
     */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 2).'***@'.$domain;
    }
}
