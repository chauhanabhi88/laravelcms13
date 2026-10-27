<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Support\Money;

/**
 * Casts a decimal(12,2) column to Money and back.
 *
 * Usage: protected function casts(): array { return ['price' => MoneyCast::class]; }
 *
 * @implements CastsAttributes<Money|null, Money|string|int|float|null>
 */
class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Money::of(is_float($value) ? $value : (string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Money) {
            return $value->toDecimal();
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return Money::of($value)->toDecimal();
        }

        throw new InvalidArgumentException("The [{$key}] attribute must be Money or a numeric amount.");
    }
}
