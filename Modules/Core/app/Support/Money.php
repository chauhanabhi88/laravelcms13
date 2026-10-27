<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use InvalidArgumentException;
use JsonSerializable;
use OverflowException;
use RoundingMode;

/**
 * An immutable INR amount held as integer paise.
 *
 * Every operation is exact integer arithmetic: decimal strings are parsed digit by
 * digit and fractional results are rounded explicitly, so no float ever touches a
 * money value. Persist with toDecimal() into a decimal(12,2) column, or use MoneyCast.
 */
final readonly class Money implements JsonSerializable
{
    /**
     * The most fractional digits accepted when parsing a decimal string.
     */
    private const MAX_SCALE = 8;

    private function __construct(public int $paise) {}

    /**
     * Build from a rupee amount: "249.50", "1,249.5", 249, 249.5.
     *
     * Extra fractional digits are rounded half away from zero to the nearest paisa.
     */
    public static function of(string|int|float $rupees): self
    {
        if (is_int($rupees)) {
            return new self(self::guard($rupees * 100));
        }

        if (is_float($rupees)) {
            if (! is_finite($rupees)) {
                throw new InvalidArgumentException('A money amount must be a finite number.');
            }

            $rupees = number_format($rupees, 2, '.', '');
        }

        [$numerator, $denominator] = self::parseDecimal(str_replace([',', '₹', ' '], '', $rupees));

        return new self(self::divide(self::guard($numerator * 100), $denominator, RoundingMode::HalfAwayFromZero));
    }

    public static function paise(int $paise): self
    {
        return new self($paise);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function plus(Money $other): self
    {
        return new self(self::guard($this->paise + $other->paise));
    }

    public function minus(Money $other): self
    {
        return new self(self::guard($this->paise - $other->paise));
    }

    /**
     * Multiply by a quantity, e.g. a line total. A decimal-string quantity ("1.5") is allowed.
     */
    public function times(int|string $quantity, RoundingMode $mode = RoundingMode::HalfAwayFromZero): self
    {
        if (is_int($quantity)) {
            return new self(self::guard($this->paise * $quantity));
        }

        [$numerator, $denominator] = self::parseDecimal($quantity);

        return new self(self::divide(self::guard($this->paise * $numerator), $denominator, $mode));
    }

    /**
     * A percentage of this amount, e.g. GST or commission: Money::of('100')->percent('18') is ₹18.
     */
    public function percent(string|int $percent, RoundingMode $mode = RoundingMode::HalfAwayFromZero): self
    {
        [$numerator, $denominator] = self::parseDecimal((string) $percent);

        return new self(self::divide(self::guard($this->paise * $numerator), self::guard($denominator * 100), $mode));
    }

    /**
     * Split pro rata by the given ratios without losing a paisa.
     *
     * Each share is rounded towards zero and the remainder goes to the largest ratio
     * (the first one on a tie), so the shares always add up to this amount.
     *
     * @param  array<array-key, int|string>  $ratios
     * @return array<array-key, Money> keyed like $ratios
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === []) {
            throw new InvalidArgumentException('At least one ratio is needed to allocate money.');
        }

        $scale = 0;
        $parsed = [];
        foreach ($ratios as $key => $ratio) {
            [$numerator, $denominator] = self::parseDecimal((string) $ratio);

            if ($numerator < 0) {
                throw new InvalidArgumentException('Allocation ratios cannot be negative.');
            }

            $parsed[$key] = [$numerator, $denominator];
            $scale = max($scale, $denominator);
        }

        $weights = array_map(fn (array $ratio): int => intdiv($ratio[0] * $scale, $ratio[1]), $parsed);
        $total = array_sum($weights);

        if ($total === 0) {
            throw new InvalidArgumentException('Allocation ratios must not all be zero.');
        }

        $sign = $this->paise < 0 ? -1 : 1;
        $amount = abs($this->paise);
        $shares = [];
        $allocated = 0;

        foreach ($weights as $key => $weight) {
            $shares[$key] = intdiv(self::guard($amount * $weight), $total);
            $allocated += $shares[$key];
        }

        $largest = array_search(max($weights), $weights, true);
        $shares[$largest] += $amount - $allocated;

        return array_map(fn (int $share): self => new self($sign * $share), $shares);
    }

    /**
     * The amount as a decimal string for the database: "249.50", "-0.05".
     */
    public function toDecimal(): string
    {
        $absolute = abs($this->paise);

        return sprintf('%s%d.%02d', $this->paise < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);
    }

    /**
     * Format with the rupee sign and Indian digit grouping: ₹1,23,456.
     *
     * Paise are shown when $showPaise is true, or whenever they are not zero.
     */
    public function format(bool $showPaise = false): string
    {
        $absolute = abs($this->paise);
        $paise = $absolute % 100;
        $formatted = '₹'.self::groupIndian((string) intdiv($absolute, 100));

        if ($showPaise || $paise !== 0) {
            $formatted .= sprintf('.%02d', $paise);
        }

        return ($this->paise < 0 ? '-' : '').$formatted;
    }

    public function isZero(): bool
    {
        return $this->paise === 0;
    }

    public function isNegative(): bool
    {
        return $this->paise < 0;
    }

    public function isPositive(): bool
    {
        return $this->paise > 0;
    }

    public function equals(Money $other): bool
    {
        return $this->paise === $other->paise;
    }

    /**
     * -1, 0 or 1 as this amount is less than, equal to or greater than the other.
     */
    public function compare(Money $other): int
    {
        return $this->paise <=> $other->paise;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    /**
     * Parse a plain decimal string into an exact fraction.
     *
     * @return array{0: int, 1: int} numerator and a power-of-ten denominator
     */
    private static function parseDecimal(string $value): array
    {
        $value = trim($value);

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $value, $matches) || ($matches[2] === '' && ($matches[3] ?? '') === '')) {
            throw new InvalidArgumentException("[{$value}] is not a valid decimal amount.");
        }

        $fraction = rtrim($matches[3] ?? '', '0');

        if (strlen($fraction) > self::MAX_SCALE) {
            throw new InvalidArgumentException("[{$value}] has more than ".self::MAX_SCALE.' decimal places.');
        }

        $digits = ltrim($matches[2].$fraction, '0');

        if (strlen($digits) > 17) {
            throw new OverflowException("[{$value}] is too large to be held as money.");
        }

        $numerator = (int) ($digits === '' ? '0' : $digits);

        return [$matches[1] === '-' ? -$numerator : $numerator, 10 ** strlen($fraction)];
    }

    /**
     * Divide two integers and round the quotient with the given mode.
     */
    private static function divide(int $numerator, int $denominator, RoundingMode $mode): int
    {
        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder === 0) {
            return $quotient;
        }

        $sign = $numerator < 0 ? -1 : 1;
        $half = (2 * abs($remainder)) <=> $denominator;

        $roundAway = match ($mode) {
            RoundingMode::HalfAwayFromZero => $half >= 0,
            RoundingMode::HalfTowardsZero => $half > 0,
            RoundingMode::HalfEven => $half > 0 || ($half === 0 && $quotient % 2 !== 0),
            RoundingMode::HalfOdd => $half > 0 || ($half === 0 && $quotient % 2 === 0),
            RoundingMode::TowardsZero => false,
            RoundingMode::AwayFromZero => true,
            RoundingMode::NegativeInfinity => $sign < 0,
            RoundingMode::PositiveInfinity => $sign > 0,
        };

        return $roundAway ? $quotient + $sign : $quotient;
    }

    /**
     * Group whole rupees the Indian way: the last three digits, then pairs.
     */
    private static function groupIndian(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $lastThree = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $rest = strlen($rest) % 2 === 1 ? '0'.$rest : $rest;
        $pairs = ltrim(implode(',', str_split($rest, 2)), '0');

        return $pairs.','.$lastThree;
    }

    /**
     * Integer arithmetic past PHP_INT_MAX silently turns into a float; refuse that.
     */
    private static function guard(int|float $value): int
    {
        if (! is_int($value)) {
            throw new OverflowException('The money amount is too large.');
        }

        return $value;
    }
}
