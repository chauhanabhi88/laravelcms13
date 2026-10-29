<?php

declare(strict_types=1);

namespace Modules\Core\Support\Api;

/**
 * Lookups over config('api_codes') (plan M20 §3.1): which numeric code a domain "reason" maps
 * to and which HTTP status a code travels with.
 */
final class ApiCodes
{
    public const TOKEN_INVALID = 4011;

    public const TOKEN_REVOKED = 4012;

    public const SELLER_SUSPENDED = 4031;

    public const PRICE_CHANGED = 4091;

    public const OUT_OF_STOCK = 4092;

    public const COD_NOT_ELIGIBLE = 4221;

    public const OTP_RATE_LIMITED = 4291;

    /**
     * The app-specific code for a domain reason, or null when it has none.
     */
    public static function forReason(string $reason): ?int
    {
        $exact = config('api_codes.reasons.'.$reason);

        if (is_int($exact)) {
            return $exact;
        }

        foreach ((array) config('api_codes.reason_prefixes', []) as $prefix => $code) {
            if (str_starts_with($reason, (string) $prefix)) {
                return (int) $code;
            }
        }

        return null;
    }

    /**
     * The HTTP status an envelope code is delivered with (4091 -> 409, 404 -> 404).
     */
    public static function httpStatus(int $code, int $default = 400): int
    {
        $known = config('api_codes.codes.'.$code.'.http');

        if (is_int($known)) {
            return $known;
        }

        return $code >= 100 && $code <= 599 ? $code : $default;
    }

    /**
     * The default (translated) message of an app-specific code.
     */
    public static function message(int $code): ?string
    {
        $key = config('api_codes.codes.'.$code.'.message');

        return is_string($key) ? trans($key) : null;
    }

    /**
     * @return array<int, string> code => name
     */
    public static function names(): array
    {
        return array_map(fn (array $row): string => (string) $row['name'], (array) config('api_codes.codes', []));
    }
}
