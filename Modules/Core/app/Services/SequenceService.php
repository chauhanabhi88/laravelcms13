<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Duplicate-free human-readable numbers (ORD-2610-000001).
 *
 * Each key is a row in number_sequence that is locked for the duration of the
 * increment, so concurrent callers queue up instead of reading the same value.
 * Called inside the caller's transaction, the lock is held until that commits and a
 * rollback returns the number, so the series also stays gap-free.
 */
class SequenceService
{
    private const TABLE = 'number_sequence';

    /**
     * Take the next number for a key: prefix followed by the zero-padded counter.
     */
    public function next(string $key, string $prefix, int $padding = 6): string
    {
        if ($key === '' || strlen($key) > 80 || strlen($prefix) > 40 || $padding < 1 || $padding > 20) {
            throw new InvalidArgumentException('Invalid sequence key, prefix or padding.');
        }

        $value = DB::transaction(function () use ($key, $prefix, $padding): int {
            DB::table(self::TABLE)->insertOrIgnore([
                'key' => $key,
                'prefix' => $prefix,
                'next_value' => 1,
                'padding' => $padding,
                'updated_at' => Date::now(),
            ]);

            $row = DB::table(self::TABLE)->where('key', $key)->lockForUpdate()->first(['id', 'next_value']);

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'next_value' => $row->next_value + 1,
                'prefix' => $prefix,
                'padding' => $padding,
                'updated_at' => Date::now(),
            ]);

            return (int) $row->next_value;
        }, 3);

        return $prefix.str_pad((string) $value, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * ORD-2610-000001, restarting every month.
     */
    public function orderNumber(?CarbonInterface $at = null): string
    {
        return $this->monthly('order', 'ORD', $at);
    }

    /**
     * RET-2610-000001, restarting every month.
     */
    public function returnNumber(?CarbonInterface $at = null): string
    {
        return $this->monthly('return', 'RET', $at);
    }

    /**
     * PAY-2610-000001, restarting every month.
     */
    public function payoutNumber(?CarbonInterface $at = null): string
    {
        return $this->monthly('payout', 'PAY', $at);
    }

    /**
     * SELLERCODE/2026-27/000001: one series per seller per financial year.
     *
     * Takes the seller's code rather than the model so Core stays independent of the Seller module.
     */
    public function invoiceNumber(string $sellerCode, ?string $financialYear = null): string
    {
        $sellerCode = strtoupper(trim($sellerCode));
        $financialYear ??= $this->financialYear();

        if (! preg_match('/^[A-Z0-9-]{1,24}$/', $sellerCode)) {
            throw new InvalidArgumentException("[{$sellerCode}] is not a valid seller code.");
        }

        return $this->next("invoice:{$sellerCode}:{$financialYear}", "{$sellerCode}/{$financialYear}/");
    }

    /**
     * The Indian financial year (April to March) a date falls in: "2026-27".
     */
    public function financialYear(?CarbonInterface $at = null): string
    {
        $at ??= Date::now();
        $startYear = $at->month >= 4 ? $at->year : $at->year - 1;

        return sprintf('%d-%02d', $startYear, ($startYear + 1) % 100);
    }

    private function monthly(string $type, string $code, ?CarbonInterface $at): string
    {
        $period = ($at ?? Date::now())->format('ym');

        return $this->next("{$type}:{$period}", "{$code}-{$period}-");
    }
}
