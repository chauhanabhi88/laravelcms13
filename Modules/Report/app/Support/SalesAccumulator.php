<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Modules\Core\Support\Money;

/**
 * Rolls item-level facts up into daily_sales_stat rows. Order counts are distinct ids per
 * grain (a seller order with items in two categories counts once in each category), money is
 * summed in paise.
 */
final class SalesAccumulator
{
    /**
     * Distinct-id sets => the count column they fill.
     */
    private const SETS = [
        'orders' => 'orders',
        'seller_orders' => 'seller_orders',
        'cod_orders' => 'cod_orders',
        'new_customers' => 'new_customers',
        'cancelled' => 'cancelled_orders',
        'cancelled_customer' => 'cancelled_by_customer',
        'cancelled_seller' => 'cancelled_by_seller',
        'cancelled_platform' => 'cancelled_by_platform',
        'open' => 'status_open',
        'delivered_status' => 'status_delivered',
        'returned_status' => 'status_returned',
        'rto_status' => 'status_rto',
        'shipped' => 'shipped_orders',
        'rto' => 'rto_orders',
        'delivered' => 'delivered_orders',
    ];

    /**
     * Summed columns (units and paise).
     */
    private const SUMS = ['units', 'gmv', 'net_revenue', 'discount', 'cancelled_gmv', 'delivered_units', 'returned_units', 'return_value'];

    /**
     * @var array<string, array<string, array<int|string, true>>>
     */
    private array $sets = [];

    /**
     * @var array<string, array<string, int>>
     */
    private array $sums = [];

    /**
     * @param  list<string>  $keys
     */
    public function mark(array $keys, string $set, int|string $id): void
    {
        foreach ($keys as $key) {
            $this->sets[$key][$set][$id] = true;
        }
    }

    /**
     * @param  list<string>  $keys
     */
    public function add(array $keys, string $column, int $amount): void
    {
        if ($amount === 0) {
            foreach ($keys as $key) {
                $this->sums[$key] ??= [];
            }

            return;
        }

        foreach ($keys as $key) {
            $this->sums[$key][$column] = ($this->sums[$key][$column] ?? 0) + $amount;
        }
    }

    public function isEmpty(): bool
    {
        return $this->sets === [] && $this->sums === [];
    }

    /**
     * Rows keyed by grain key, with money as decimal strings (as stored).
     *
     * @return array<string, array<string, int|string>>
     */
    public function rows(): array
    {
        $rows = [];

        foreach (array_unique([...array_keys($this->sets), ...array_keys($this->sums)]) as $key) {
            $row = [];

            foreach (self::SETS as $set => $column) {
                $row[$column] = count($this->sets[$key][$set] ?? []);
            }

            foreach (self::SUMS as $column) {
                $value = $this->sums[$key][$column] ?? 0;
                $row[$column] = in_array($column, Metrics::MONEY, true) ? Money::paise($value)->toDecimal() : $value;
            }

            $rows[$key] = $row;
        }

        ksort($rows);

        return $rows;
    }
}
