<?php

declare(strict_types=1);

namespace Modules\Seller\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * GSTIN: 2-digit state code, the holder's PAN, entity number, "Z", check character.
 *
 * Checks the format, that the state code exists, the mod-36 check character and, when a
 * PAN is given, that the GSTIN embeds that PAN (characters 3–12).
 */
class Gstin implements ValidationRule
{
    public const PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    private const CHARSET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * GST state code => ISO 3166-2:IN code (directory_state.code). 25 (Daman & Diu) and 26
     * (Dadra & Nagar Haveli) merged into one UT in 2020; 28 and 37 are both Andhra Pradesh
     * (before / after 2014). 97 (other territory) and 99 (centre jurisdiction) have no state.
     */
    public const STATE_CODES = [
        '01' => 'JK', '02' => 'HP', '03' => 'PB', '04' => 'CH', '05' => 'UK', '06' => 'HR',
        '07' => 'DL', '08' => 'RJ', '09' => 'UP', '10' => 'BR', '11' => 'SK', '12' => 'AR',
        '13' => 'NL', '14' => 'MN', '15' => 'MZ', '16' => 'TR', '17' => 'ML', '18' => 'AS',
        '19' => 'WB', '20' => 'JH', '21' => 'OD', '22' => 'CG', '23' => 'MP', '24' => 'GJ',
        '25' => 'DH', '26' => 'DH', '27' => 'MH', '28' => 'AP', '29' => 'KA', '30' => 'GA',
        '31' => 'LD', '32' => 'KL', '33' => 'TN', '34' => 'PY', '35' => 'AN', '36' => 'TG',
        '37' => 'AP', '38' => 'LA', '97' => null, '99' => null,
    ];

    public function __construct(private readonly ?string $pan = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $gstin = static::normalise($value);

        if (! preg_match(self::PATTERN, $gstin)) {
            $fail('seller::validation.gstin.format')?->translate();

            return;
        }

        if (! array_key_exists(substr($gstin, 0, 2), self::STATE_CODES)) {
            $fail('seller::validation.gstin.state')?->translate();

            return;
        }

        if (! static::hasValidChecksum($gstin)) {
            $fail('seller::validation.gstin.checksum')?->translate();

            return;
        }

        if ($this->pan !== null && $this->pan !== '' && static::embeddedPan($gstin) !== strtoupper(trim($this->pan))) {
            $fail('seller::validation.gstin.pan_mismatch')?->translate();
        }
    }

    public static function normalise(mixed $value): string
    {
        return is_string($value) ? strtoupper(trim($value)) : '';
    }

    public static function isValid(string $gstin): bool
    {
        $gstin = static::normalise($gstin);

        return preg_match(self::PATTERN, $gstin) === 1
            && array_key_exists(substr($gstin, 0, 2), self::STATE_CODES)
            && static::hasValidChecksum($gstin);
    }

    /**
     * The check character for the first 14 characters: each character's base-36 value is
     * multiplied by 1 and 2 alternately, quotient and remainder (by 36) of every product are
     * summed, and the check value is (36 − sum mod 36) mod 36.
     */
    public static function checksum(string $first14): string
    {
        $sum = 0;

        foreach (str_split(substr(strtoupper($first14), 0, 14)) as $index => $character) {
            $product = (int) strpos(self::CHARSET, $character) * ($index % 2 === 0 ? 1 : 2);
            $sum += intdiv($product, 36) + ($product % 36);
        }

        return self::CHARSET[(36 - ($sum % 36)) % 36];
    }

    /**
     * Append the check character to a 14-character GSTIN body.
     */
    public static function withChecksum(string $first14): string
    {
        $first14 = substr(strtoupper($first14), 0, 14);

        return $first14.static::checksum($first14);
    }

    public static function hasValidChecksum(string $gstin): bool
    {
        return strlen($gstin) === 15 && static::checksum($gstin) === $gstin[14];
    }

    public static function embeddedPan(string $gstin): string
    {
        return substr(static::normalise($gstin), 2, 10);
    }

    public static function stateCode(string $gstin): string
    {
        return substr(static::normalise($gstin), 0, 2);
    }

    /**
     * ISO 3166-2:IN code of the GSTIN's state, or null for 97/99 and unknown codes.
     */
    public static function isoStateCode(string $gstin): ?string
    {
        return self::STATE_CODES[static::stateCode($gstin)] ?? null;
    }
}
