<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

use InvalidArgumentException;
use Modules\Core\Services\Otp\OtpService;

/**
 * Mobile numbers are stored in E.164 (+919876543210), the form OTP codes are keyed by.
 */
class Phone
{
    public static function normalise(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return OtpService::normaliseIdentifier(OtpService::CHANNEL_SMS, $value);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * +919876543210 → 98765 43210 for display.
     */
    public static function display(?string $e164): string
    {
        if ($e164 === null || ! str_starts_with($e164, '+91') || strlen($e164) !== 13) {
            return (string) $e164;
        }

        return substr($e164, 3, 5).' '.substr($e164, 8);
    }
}
