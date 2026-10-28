<?php

declare(strict_types=1);

namespace Modules\Returns\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An Indian bank account number for a COD refund: 9–18 digits (spaces ignored).
 */
class BankAccount implements ValidationRule
{
    public const PATTERN = '/^[0-9]{9,18}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid(is_string($value) ? $value : '')) {
            $fail('returns::front.validation.bank_account')->translate();
        }
    }

    public static function isValid(string $number): bool
    {
        return preg_match(self::PATTERN, (string) preg_replace('/\s+/', '', $number)) === 1;
    }
}
