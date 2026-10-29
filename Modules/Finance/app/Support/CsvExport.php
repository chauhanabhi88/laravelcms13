<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streamed CSV downloads for the finance screens. Cells that a spreadsheet would treat as a
 * formula (=, +, -, @) are prefixed with a quote.
 */
final class CsvExport
{
    /**
     * @param  list<string>  $header
     * @param  Closure(): iterable<array<int, mixed>>  $rows
     */
    public static function stream(string $filename, array $header, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, escape: '\\');

            foreach ($rows() as $row) {
                fputcsv($out, array_map(self::cell(...), $row), escape: '\\');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function cell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Negative amounts stay numbers; other leading formula characters are neutralised.
        if ($value !== '' && in_array($value[0], ['=', '+', '@'], true) || ($value !== '' && $value[0] === '-' && ! is_numeric($value))) {
            return "'".$value;
        }

        return $value;
    }
}
