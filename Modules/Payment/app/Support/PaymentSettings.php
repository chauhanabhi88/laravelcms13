<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Modules\Core\Support\Money;

/**
 * Typed access to admin > Settings > Payment with config('payment.defaults') fallbacks, so
 * callers never read settings() without a default.
 */
final class PaymentSettings
{
    public static function codEnabled(): bool
    {
        return self::flag('cod_enabled');
    }

    public static function codMinValue(): Money
    {
        return self::money('cod_min_value');
    }

    public static function codMaxValue(): Money
    {
        return self::money('cod_max_value');
    }

    /**
     * COD orders a customer may place per calendar day (0 = no limit).
     */
    public static function codDailyLimit(): int
    {
        return self::integer('cod_daily_limit');
    }

    /**
     * Orders from this value up need a COD OTP (0 = every COD order).
     */
    public static function codOtpThreshold(): Money
    {
        return self::money('cod_otp_threshold');
    }

    public static function codOtpForNewCustomers(): bool
    {
        return self::flag('cod_otp_new_customers');
    }

    public static function paymentWindowMinutes(): int
    {
        return max(1, self::integer('payment_window_minutes'));
    }

    public static function codOtpWindowMinutes(): int
    {
        return max(1, self::integer('cod_otp_window_minutes'));
    }

    /**
     * The prepaid gateway driver checkout uses (config, not an admin setting: it needs keys).
     */
    public static function defaultGateway(): string
    {
        $gateway = (string) config('payment.default_gateway');

        return in_array($gateway, (array) config('payment.gateways', []), true) ? $gateway : 'razorpay';
    }

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('payment.defaults.'.$key)) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('payment.defaults.'.$key)));
    }

    /**
     * A non-negative amount; blank or invalid values use the default.
     */
    protected static function money(string $key): Money
    {
        $value = self::get($key);
        $amount = is_numeric($value) ? Money::of((string) $value) : Money::of((string) config('payment.defaults.'.$key, '0'));

        return $amount->isNegative() ? Money::zero() : $amount;
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('payment', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
