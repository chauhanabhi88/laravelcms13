<?php

declare(strict_types=1);

namespace Modules\Report\Support;

/**
 * The stored grains of daily_sales_stat (plan M19 §3.1): platform total, platform × L1
 * category, platform × shipping state, platform × payment and seller × all. A grain key is
 * "seller|category|state|payment", e.g. "0|0|0|all" for the platform total.
 */
final class Grain
{
    public const ALL = 'all';

    public const COD = 'cod';

    public const PREPAID = 'prepaid';

    public const PAYMENTS = [self::COD, self::PREPAID];

    public const DIMENSION_SELLER = 'seller';

    public const DIMENSION_CATEGORY = 'category';

    public const DIMENSION_STATE = 'state';

    public const DIMENSION_PAYMENT = 'payment';

    public const DIMENSIONS = [self::DIMENSION_SELLER, self::DIMENSION_CATEGORY, self::DIMENSION_STATE, self::DIMENSION_PAYMENT];

    public const PLATFORM = '0|0|0|all';

    /**
     * Every stored grain a fact with these attributes adds to. A fact without a category
     * (deleted product) or a state still counts in the other grains.
     *
     * @return list<string>
     */
    public static function keysFor(int $sellerId, ?int $categoryId, ?int $stateId, string $payment, bool $withCategory = true): array
    {
        $keys = [self::PLATFORM];

        if ($withCategory && $categoryId !== null && $categoryId > 0) {
            $keys[] = self::key(0, $categoryId, 0, self::ALL);
        }

        if ($stateId !== null && $stateId > 0) {
            $keys[] = self::key(0, 0, $stateId, self::ALL);
        }

        $keys[] = self::key(0, 0, 0, $payment);

        if ($sellerId > 0) {
            $keys[] = self::key($sellerId, 0, 0, self::ALL);
        }

        return $keys;
    }

    public static function key(int $sellerId, int $categoryId, int $stateId, string $payment): string
    {
        return $sellerId.'|'.$categoryId.'|'.$stateId.'|'.$payment;
    }

    /**
     * @return array{seller_id: int, catalog_category_id: int, state_id: int, payment_method: string}
     */
    public static function split(string $key): array
    {
        [$seller, $category, $state, $payment] = explode('|', $key);

        return [
            'seller_id' => (int) $seller,
            'catalog_category_id' => (int) $category,
            'state_id' => (int) $state,
            'payment_method' => $payment,
        ];
    }

    /**
     * Grain columns of one dimension's rows, with the varying column named.
     *
     * @return array{column: string, fixed: array<string, int|string>}
     */
    public static function dimension(string $dimension): array
    {
        $fixed = ['seller_id' => 0, 'catalog_category_id' => 0, 'state_id' => 0, 'payment_method' => self::ALL];

        $column = match ($dimension) {
            self::DIMENSION_SELLER => 'seller_id',
            self::DIMENSION_CATEGORY => 'catalog_category_id',
            self::DIMENSION_STATE => 'state_id',
            self::DIMENSION_PAYMENT => 'payment_method',
        };

        unset($fixed[$column]);

        return ['column' => $column, 'fixed' => $fixed];
    }

    public static function paymentOf(?string $method): string
    {
        return $method === self::COD ? self::COD : self::PREPAID;
    }

    /**
     * The L1 category id of a materialised path ("/1/7/23/" → 1).
     */
    public static function rootCategory(?string $path): ?int
    {
        $first = explode('/', trim((string) $path, '/'))[0] ?? '';

        return ctype_digit($first) ? (int) $first : null;
    }
}
