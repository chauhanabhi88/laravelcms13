<?php

declare(strict_types=1);

namespace Modules\Seller\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Permanent Account Number: five letters, four digits, one letter. The fourth letter is
 * the holder type (P person, C company, H HUF, F firm, A AOP, T trust, B BOI, L local
 * authority, J artificial juridical person, G government).
 */
class Pan implements ValidationRule
{
    public const PATTERN = '/^[A-Z]{3}[ABCFGHJLPT][A-Z][0-9]{4}[A-Z]$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid(is_string($value) ? $value : '')) {
            $fail('seller::validation.pan.format')?->translate();
        }
    }

    public static function isValid(string $pan): bool
    {
        return preg_match(self::PATTERN, strtoupper(trim($pan))) === 1;
    }
}
