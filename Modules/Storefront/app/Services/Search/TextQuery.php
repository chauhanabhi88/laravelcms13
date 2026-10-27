<?php

declare(strict_types=1);

namespace Modules\Storefront\Services\Search;

/**
 * Turns user input into safe full-text and LIKE terms. Boolean-mode operators and other
 * punctuation are dropped, so input can never change the meaning of a MATCH expression.
 */
final class TextQuery
{
    /**
     * Words of the query: letters and digits only, lower case, at most 10.
     *
     * @return list<string>
     */
    public static function tokens(?string $text): array
    {
        $clean = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower((string) $text));
        $tokens = array_values(array_unique(array_filter(explode(' ', $clean), fn (string $token): bool => $token !== '')));

        return array_slice($tokens, 0, 10);
    }

    /**
     * Words long enough for the InnoDB full-text index (innodb_ft_min_token_size).
     *
     * @return list<string>
     */
    public static function indexableTokens(?string $text): array
    {
        $min = self::minTokenSize();

        return array_values(array_filter(self::tokens($text), fn (string $token): bool => mb_strlen($token) >= $min));
    }

    /**
     * Natural-language search string.
     */
    public static function natural(?string $text): string
    {
        return implode(' ', self::indexableTokens($text));
    }

    /**
     * Boolean-mode string requiring every word as a prefix: "+kur* +cot*".
     */
    public static function booleanPrefix(?string $text): string
    {
        return implode(' ', array_map(fn (string $token): string => '+'.$token.'*', self::indexableTokens($text)));
    }

    /**
     * A LIKE pattern for one token, with the wildcards escaped.
     */
    public static function like(string $token): string
    {
        return '%'.self::escapeLike($token).'%';
    }

    /**
     * A "starts with" LIKE pattern, with the wildcards escaped.
     */
    public static function prefixLike(string $text): string
    {
        return self::escapeLike($text).'%';
    }

    protected static function escapeLike(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }

    public static function minTokenSize(): int
    {
        return max(1, (int) config('storefront.fulltext_min_token_size', 3));
    }
}
