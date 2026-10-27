<?php

declare(strict_types=1);

namespace Modules\Seller\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Indian Financial System Code format: four-letter bank code, a zero, six-character branch
 * code. Whether the branch exists is checked separately with Modules\Seller\Support\IfscLookup.
 */
class Ifsc implements ValidationRule
{
    public const PATTERN = '/^[A-Z]{4}0[A-Z0-9]{6}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid(is_string($value) ? $value : '')) {
            $fail('seller::validation.ifsc.format')?->translate();
        }
    }

    public static function isValid(string $ifsc): bool
    {
        return preg_match(self::PATTERN, strtoupper(trim($ifsc))) === 1;
    }
}
