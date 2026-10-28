<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use BackedEnum;
use Stringable;

/**
 * Fills {{placeholder}} templates. Unknown placeholders render empty; HTML output escapes
 * every value; SMS text is cut to a number of concatenated segments.
 *
 * Has no framework dependencies, so it is unit-testable on its own.
 */
final class TemplateRenderer
{
    private const PLACEHOLDER = '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/';

    /**
     * GSM 03.38 basic character set (one septet each).
     */
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /**
     * GSM 03.38 extension table (two septets each: escape + character).
     */
    private const GSM_EXTENDED = '^{}\\[~]|€';

    private const ELLIPSIS = '...';

    /**
     * @param  array<string, mixed>  $vars
     */
    public function render(?string $template, array $vars, bool $escapeHtml = false): string
    {
        if ($template === null || $template === '') {
            return '';
        }

        return (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($vars, $escapeHtml): string {
            $value = self::stringify($vars[$match[1]] ?? null);

            return $escapeHtml ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
        }, $template);
    }

    /**
     * Placeholder names used by a template, in order of first use.
     *
     * @return list<string>
     */
    public function placeholders(?string $template): array
    {
        if ($template === null || ! preg_match_all(self::PLACEHOLDER, $template, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    /**
     * Plain text for SMS / push: tags stripped, whitespace collapsed.
     *
     * @param  array<string, mixed>  $vars
     */
    public function renderText(?string $template, array $vars): string
    {
        $text = strip_tags($this->render($template, $vars));

        return trim((string) preg_replace('/[ \t]+/u', ' ', $text));
    }

    /**
     * Cut a message to $segments SMS parts: GSM-7 text fits 160 characters in one part or
     * 153 per part when concatenated (306 for two); anything else is UCS-2 with 70 / 67.
     */
    public function limitSms(string $text, int $segments = 2): string
    {
        $segments = max(1, $segments);
        $gsm = self::isGsm7($text);
        $single = $gsm ? 160 : 70;
        $limit = $segments === 1 ? $single : ($gsm ? 153 : 67) * $segments;

        if (self::smsLength($text) <= max($single, $limit)) {
            return $text;
        }

        $budget = $limit - self::smsLength(self::ELLIPSIS);
        $result = '';
        $used = 0;

        foreach (mb_str_split($text) as $character) {
            $cost = $gsm ? self::gsmCost($character) : self::smsLength($character);

            if ($used + $cost > $budget) {
                break;
            }

            $result .= $character;
            $used += $cost;
        }

        return rtrim($result).self::ELLIPSIS;
    }

    public static function isGsm7(string $text): bool
    {
        foreach (mb_str_split($text) as $character) {
            if (! str_contains(self::GSM_BASIC, $character) && ! str_contains(self::GSM_EXTENDED, $character)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Length in SMS characters: septets for GSM-7 text, UCS-2 code units otherwise.
     */
    public static function smsLength(string $text): int
    {
        if (! self::isGsm7($text)) {
            return (int) (strlen((string) mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')) / 2);
        }

        $length = 0;

        foreach (mb_str_split($text) as $character) {
            $length += self::gsmCost($character);
        }

        return $length;
    }

    private static function gsmCost(string $character): int
    {
        return str_contains(self::GSM_EXTENDED, $character) ? 2 : 1;
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null, is_array($value) => '',
            is_bool($value) => $value ? '1' : '',
            $value instanceof BackedEnum => (string) $value->value,
            is_scalar($value), $value instanceof Stringable => (string) $value,
            default => '',
        };
    }
}
