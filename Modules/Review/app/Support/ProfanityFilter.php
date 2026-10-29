<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Illuminate\Support\Str;

/**
 * Word-list profanity check for reviews and seller replies (plan M16 §5). The lists live in
 * the lang files (review::profanity.words) per locale; every installed list is applied, so a
 * Hindi word is caught in an English review too.
 *
 * Text is folded before matching: lower case, common digit/symbol swaps (@ → a, 0 → o…),
 * repeated letters squeezed ("baaad" → "bad") and dots / dashes between single letters
 * removed ("b.a.d" → "bad"). Whole words only, so "class" never matches "ass".
 */
class ProfanityFilter
{
    private const SWAPS = ['@' => 'a', '4' => 'a', '3' => 'e', '1' => 'i', '!' => 'i', '0' => 'o', '$' => 's', '5' => 's', '7' => 't'];

    /**
     * @var list<string>|null
     */
    private ?array $words = null;

    public function contains(?string $text): bool
    {
        return $this->matches($text) !== [];
    }

    /**
     * The listed words found in the text.
     *
     * @return list<string>
     */
    public function matches(?string $text): array
    {
        $text = trim((string) $text);

        if ($text === '' || $this->words() === []) {
            return [];
        }

        $haystack = ' '.implode(' ', $this->tokens($text)).' ';
        $found = [];

        foreach ($this->words() as $word) {
            if (str_contains($haystack, ' '.$word.' ')) {
                $found[] = $word;
            }
        }

        return $found;
    }

    /**
     * @return list<string> folded words of the text
     */
    protected function tokens(string $text): array
    {
        $text = Str::lower($text);
        $text = strtr($text, self::SWAPS);
        // "b.a.d" / "b-a-d" → "bad": separators between single letters (not spaces, which would
        // glue a word like "a" in "a b.a.d" onto it).
        $text = (string) preg_replace_callback('/(?<![\p{L}\p{M}])(?:[\p{L}\p{M}][._*-]){2,}[\p{L}\p{M}](?![\p{L}\p{M}])/u', fn (array $match): string => (string) preg_replace('/[._*-]/u', '', $match[0]), $text);
        $words = preg_split('/[^\p{L}\p{M}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(fn (string $word): string => $this->squeeze($word), $words));
    }

    protected function squeeze(string $word): string
    {
        return (string) preg_replace('/(\p{L})\1{2,}/u', '$1', $word);
    }

    /**
     * @return list<string>
     */
    protected function words(): array
    {
        if ($this->words !== null) {
            return $this->words;
        }

        $words = [];

        foreach ($this->locales() as $locale) {
            $list = trans('review::profanity.words', [], $locale);

            if (is_array($list)) {
                array_push($words, ...array_map('strval', $list));
            }
        }

        $folded = array_map(fn (string $word): string => implode(' ', $this->tokens($word)), $words);

        return $this->words = array_values(array_unique(array_filter($folded, fn (string $word): bool => $word !== '')));
    }

    /**
     * @return list<string>
     */
    protected function locales(): array
    {
        return array_values(array_unique(array_filter([app()->getLocale(), (string) config('app.fallback_locale'), 'en', 'hi'])));
    }
}
