<?php

declare(strict_types=1);

namespace Modules\Returns\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A UPI id (virtual payment address) for a COD refund: handle@provider (plan M15 §4).
 */
class Vpa implements ValidationRule
{
    public const PATTERN = '/^[a-zA-Z0-9.\-_]{2,256}@[a-zA-Z]{2,64}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isValid(is_string($value) ? $value : '')) {
            $fail('returns::front.validation.vpa')->translate();
        }
    }

    public static function isValid(string $vpa): bool
    {
        return preg_match(self::PATTERN, trim($vpa)) === 1;
    }
}
