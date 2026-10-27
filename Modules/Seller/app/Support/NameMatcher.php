<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

/**
 * Similarity (0–100) between the name a bank returned for an account and the name the
 * seller gave: normalised Levenshtein distance over upper-cased, punctuation-free names
 * with honorifics and business suffixes removed and words sorted, so "M/s. Shree
 * Traders Pvt Ltd" matches "SHREE TRADERS PRIVATE LIMITED".
 */
class NameMatcher
{
    private const NOISE_WORDS = [
        'MR', 'MRS', 'MS', 'MISS', 'DR', 'SHRI', 'SMT', 'KUMARI', 'M', 'S', 'THE',
        'PVT', 'PRIVATE', 'LTD', 'LIMITED', 'LLP', 'CO', 'COMPANY', 'AND', 'ENTERPRISES', 'ENTERPRISE',
    ];

    public static function score(?string $first, ?string $second): int
    {
        $first = static::normalise((string) $first);
        $second = static::normalise((string) $second);

        if ($first === '' || $second === '') {
            return 0;
        }

        if ($first === $second) {
            return 100;
        }

        $length = max(strlen($first), strlen($second));

        return (int) max(0, round((1 - levenshtein($first, $second) / $length) * 100));
    }

    /**
     * Best score against any of the candidate names (holder name, legal name, store name).
     *
     * @param  array<int, string|null>  $candidates
     */
    public static function bestScore(?string $name, array $candidates): int
    {
        $best = 0;

        foreach ($candidates as $candidate) {
            $best = max($best, static::score($name, $candidate));
        }

        return $best;
    }

    public static function normalise(string $name): string
    {
        $name = strtoupper($name);
        $name = (string) preg_replace('/[^A-Z0-9 ]+/', ' ', $name);

        $words = array_values(array_filter(
            preg_split('/\s+/', $name) ?: [],
            fn (string $word): bool => $word !== '' && ! in_array($word, self::NOISE_WORDS, true),
        ));

        sort($words);

        return implode(' ', $words);
    }
}
