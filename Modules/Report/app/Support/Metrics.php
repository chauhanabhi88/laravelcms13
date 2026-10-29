<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Modules\Core\Support\Money;

/**
 * Metric definitions (plan M19 §5; the wording is in lang report::report.metrics.*.hint):
 * - GMV = Σ (price × qty − discount_allocated) of the items of orders placed in the period,
 *   leaving out seller orders cancelled before confirmation (payment failures, COD OTP expiry)
 *   and zero-value exchange orders.
 * - Net GMV = GMV − cancelled value − returned value. AOV = GMV / orders.
 * - RTO rate = RTO seller orders / seller orders shipped (cohort by ship date).
 * - Return rate = returned units / delivered units (cohort by delivery date).
 * - Cancellation rate = cancelled seller orders / seller orders placed.
 * - COD share = COD orders / orders. Net revenue = commission + platform fees, excluding GST.
 *
 * Sums are plain arrays: counts as int, money columns in paise (int).
 */
final class Metrics
{
    /**
     * Summable columns of daily_sales_stat.
     */
    public const COUNTS = [
        'orders', 'seller_orders', 'units', 'cod_orders', 'new_customers',
        'cancelled_orders', 'cancelled_by_customer', 'cancelled_by_seller', 'cancelled_by_platform',
        'status_open', 'status_delivered', 'status_returned', 'status_rto',
        'shipped_orders', 'rto_orders', 'delivered_orders', 'delivered_units', 'returned_units',
    ];

    public const MONEY = ['gmv', 'net_revenue', 'discount', 'cancelled_gmv', 'return_value'];

    /**
     * KPI tiles of the overview, in display order: [metric, format, higher is better].
     */
    public const KPI_TILES = [
        ['gmv', 'money', true],
        ['orders', 'count', true],
        ['aov', 'money', true],
        ['net_revenue', 'money', true],
        ['rto_rate', 'percent', false],
        ['return_rate', 'percent', false],
        ['cancellation_rate', 'percent', false],
        ['cod_share', 'percent', null],
    ];

    /**
     * @return array<string, int>
     */
    public static function zero(): array
    {
        return array_fill_keys([...self::COUNTS, ...self::MONEY], 0);
    }

    /**
     * A stored or live row (decimal strings for money) as sums.
     *
     * @param  array<string, mixed>|object  $row
     * @return array<string, int>
     */
    public static function normalize(array|object $row): array
    {
        $row = (array) $row;
        $sums = self::zero();

        foreach (self::COUNTS as $column) {
            $sums[$column] = (int) ($row[$column] ?? 0);
        }

        foreach (self::MONEY as $column) {
            $sums[$column] = Money::of((string) ($row[$column] ?? '0'))->paise;
        }

        return $sums;
    }

    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     * @return array<string, int>
     */
    public static function add(array $a, array $b): array
    {
        foreach ($b as $column => $value) {
            $a[$column] = ($a[$column] ?? 0) + $value;
        }

        return $a;
    }

    /**
     * Every reported value of a sums array: money as Money, rates in percent (null without a
     * denominator), counts as int.
     *
     * @param  array<string, int>  $sums
     * @return array<string, Money|float|int|null>
     */
    public static function derive(array $sums): array
    {
        $gmv = Money::paise($sums['gmv']);
        $prepaid = max(0, $sums['orders'] - $sums['cod_orders']);

        return [
            'gmv' => $gmv,
            'net_gmv' => Money::paise($sums['gmv'] - $sums['cancelled_gmv'] - $sums['return_value']),
            'net_revenue' => Money::paise($sums['net_revenue']),
            'discount' => Money::paise($sums['discount']),
            'cancelled_gmv' => Money::paise($sums['cancelled_gmv']),
            'return_value' => Money::paise($sums['return_value']),
            'aov' => $sums['orders'] > 0 ? Money::paise(intdiv($sums['gmv'], $sums['orders'])) : Money::zero(),
            'orders' => $sums['orders'],
            'seller_orders' => $sums['seller_orders'],
            'units' => $sums['units'],
            'new_customers' => $sums['new_customers'],
            'cod_orders' => $sums['cod_orders'],
            'prepaid_orders' => $prepaid,
            'shipped_orders' => $sums['shipped_orders'],
            'rto_orders' => $sums['rto_orders'],
            'delivered_orders' => $sums['delivered_orders'],
            'delivered_units' => $sums['delivered_units'],
            'returned_units' => $sums['returned_units'],
            'cancelled_orders' => $sums['cancelled_orders'],
            'cancelled_by_customer' => $sums['cancelled_by_customer'],
            'cancelled_by_seller' => $sums['cancelled_by_seller'],
            'cancelled_by_platform' => $sums['cancelled_by_platform'],
            'rto_rate' => self::rate($sums['rto_orders'], $sums['shipped_orders']),
            'return_rate' => self::rate($sums['returned_units'], $sums['delivered_units']),
            'cancellation_rate' => self::rate($sums['cancelled_orders'], $sums['seller_orders']),
            'cod_share' => self::rate($sums['cod_orders'], $sums['orders']),
        ];
    }

    public static function rate(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : null;
    }

    /**
     * A derived value as a plain number for charts and exports (rupees for money).
     */
    public static function number(Money|float|int|null $value): float|int|null
    {
        return $value instanceof Money ? (float) $value->toDecimal() : $value;
    }

    /**
     * Change against the comparison value: relative percent for money and counts, percentage
     * points for rates. Null when there is nothing to compare with.
     */
    public static function delta(Money|float|int|null $current, Money|float|int|null $previous, string $format): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $current = (float) self::number($current);
        $previous = (float) self::number($previous);

        if ($format === 'percent') {
            return round($current - $previous, 1);
        }

        return $previous != 0.0 ? round(($current - $previous) * 100 / abs($previous), 1) : null;
    }
}
