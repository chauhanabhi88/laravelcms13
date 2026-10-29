<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Modules\Core\Support\Money;

/**
 * Typed access to admin > Settings > Finance with config('finance.defaults') fallbacks, so
 * callers never read settings() without a default.
 */
final class FinanceSettings
{
    /**
     * Days after the return window closes before a sale becomes payable.
     */
    public static function payoutDelayDays(): int
    {
        return min(90, self::integer('payout_delay_days'));
    }

    /**
     * Weekday of the scheduled payout batch ("monday").
     */
    public static function payoutDay(): string
    {
        $day = strtolower((string) (self::get('payout_day') ?: config('finance.defaults.payout_day', 'monday')));

        return in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], true) ? $day : 'monday';
    }

    public static function minPayoutAmount(): Money
    {
        return self::money('min_payout_amount');
    }

    public static function tcsRate(): string
    {
        return self::percent('tcs_rate');
    }

    public static function tdsRate(): string
    {
        return self::percent('tds_rate');
    }

    public static function tdsRateWithoutPan(): string
    {
        return self::percent('tds_rate_no_pan');
    }

    public static function feeGstRate(): string
    {
        return self::percent('fee_gst_rate');
    }

    public static function sellerCancelPenalty(): Money
    {
        return self::money('seller_cancel_penalty');
    }

    public static function slaBreachPenalty(): Money
    {
        return self::money('sla_breach_penalty');
    }

    public static function sellerPaysForwardShipping(): bool
    {
        return self::flag('seller_pays_forward_shipping');
    }

    public static function sellerPaysRtoShipping(): bool
    {
        return self::flag('seller_pays_rto_shipping');
    }

    /**
     * Open claims at or above this count hold the seller's payouts; 0 = off.
     */
    public static function holdOpenClaims(): int
    {
        return self::integer('hold_open_claims');
    }

    public static function payoutGateway(): string
    {
        $gateway = (string) (self::get('payout_gateway') ?: config('finance.defaults.payout_gateway', 'manual'));

        return in_array($gateway, (array) config('finance.payout_gateways', []), true) ? $gateway : 'manual';
    }

    public static function resellerMinWithdrawal(): Money
    {
        return self::money('reseller_min_withdrawal');
    }

    /**
     * The platform as the supplier on fee invoices.
     *
     * @return array{legal_name: string, gstin: string, address: string, state_id: int|null}
     */
    public static function platform(): array
    {
        $stateId = self::get('platform_state_id');

        return [
            'legal_name' => (string) (self::get('platform_legal_name') ?: config('app.name')),
            'gstin' => strtoupper(trim((string) self::get('platform_gstin'))),
            'address' => (string) self::get('platform_address'),
            'state_id' => is_numeric($stateId) && (int) $stateId > 0 ? (int) $stateId : null,
        ];
    }

    protected static function flag(string $key): bool
    {
        $value = self::get($key);

        return (int) (is_numeric($value) ? $value : config('finance.defaults.'.$key)) === (int) config('core.yes');
    }

    protected static function integer(string $key): int
    {
        $value = self::get($key);

        return max(0, (int) (is_numeric($value) ? $value : config('finance.defaults.'.$key)));
    }

    protected static function money(string $key): Money
    {
        $value = self::get($key);
        $value = is_numeric($value) && (float) $value >= 0 ? (string) $value : (string) config('finance.defaults.'.$key, '0');

        return Money::of($value);
    }

    /**
     * A non-negative percentage as a decimal string ("0.1").
     */
    protected static function percent(string $key): string
    {
        $value = self::get($key);
        $value = is_numeric($value) && (float) $value >= 0 && (float) $value <= 100 ? (string) $value : (string) config('finance.defaults.'.$key, '0');

        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.') ?: '0';
    }

    protected static function get(string $key): mixed
    {
        try {
            return settings('finance', $key);
        } catch (\Throwable) {
            return null;
        }
    }
}
