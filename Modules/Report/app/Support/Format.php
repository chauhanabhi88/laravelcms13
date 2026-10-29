<?php

declare(strict_types=1);

namespace Modules\Report\Support;

use Modules\Core\Support\Money;

/**
 * Display and export formatting of report values. Formats: money, count, percent, date, text.
 */
final class Format
{
    public const MONEY = 'money';

    public const COUNT = 'count';

    public const PERCENT = 'percent';

    public const DATE = 'date';

    public const TEXT = 'text';

    public static function display(mixed $value, string $format): string
    {
        if ($value === null) {
            return '—';
        }

        return match ($format) {
            self::MONEY => $value instanceof Money ? $value->format() : Money::of((string) $value)->format(),
            self::COUNT => number_format((int) $value),
            self::PERCENT => rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%',
            default => (string) $value,
        };
    }

    /**
     * A plain value for CSV/Excel: money as a decimal string, rates as numbers.
     */
    public static function export(mixed $value, string $format): string|int|float
    {
        if ($value === null) {
            return '';
        }

        return match ($format) {
            self::MONEY => $value instanceof Money ? $value->toDecimal() : Money::of((string) $value)->toDecimal(),
            self::COUNT => (int) $value,
            self::PERCENT => round((float) $value, 1),
            default => (string) $value,
        };
    }

    /**
     * A signed change: "+12.5%" for relative changes, "+1.2 pp" for rates.
     */
    public static function delta(?float $delta, string $format): ?string
    {
        if ($delta === null) {
            return null;
        }

        $number = rtrim(rtrim(number_format(abs($delta), 1), '0'), '.');
        $sign = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');

        return $format === self::PERCENT ? $sign.$number.' pp' : $sign.$number.'%';
    }
}
