<?php

declare(strict_types=1);

namespace Modules\Product\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads the first sheet of an upload (xlsx or csv) in chunks of product.bulk_chunk_rows
 * rows and keeps every row as plain strings keyed by spreadsheet row number. Row 1 is the
 * heading row; blank rows are dropped. Reading stops counting at $maxRows data rows
 * (tooManyRows() then reports it).
 */
abstract class SpreadsheetRowsImport implements ToCollection, WithCalculatedFormulas, WithChunkReading, WithMultipleSheets
{
    /**
     * @var list<string>
     */
    protected array $headings = [];

    /**
     * @var array<int, list<string>> row number => cells
     */
    protected array $rows = [];

    protected int $rowNumber = 0;

    protected int $dataRows = 0;

    public function __construct(protected int $maxRows) {}

    /**
     * @return array<int, self>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function chunkSize(): int
    {
        return max(1, (int) config('product.bulk_chunk_rows', 500));
    }

    /**
     * @param  Collection<int, Collection<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $cells) {
            $this->rowNumber++;
            $values = array_map(fn ($value): string => self::cell($value), $cells->all());

            if ($this->rowNumber === 1) {
                $this->headings = $values;

                continue;
            }

            if (implode('', $values) === '') {
                continue;
            }

            $this->dataRows++;

            if ($this->dataRows <= $this->maxRows) {
                $this->rows[$this->rowNumber] = $values;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return array<int, list<string>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function dataRowCount(): int
    {
        return $this->dataRows;
    }

    public function tooManyRows(): bool
    {
        return $this->dataRows > $this->maxRows;
    }

    /**
     * @param  array<int, string>  $map  position => column key
     * @return array<int, array<string, string>> row number => key => value
     */
    public function keyedRows(array $map): array
    {
        $keyed = [];

        foreach ($this->rows as $rowNumber => $cells) {
            $row = [];
            foreach ($map as $position => $key) {
                $row[$key] = $cells[$position] ?? '';
            }
            $keyed[$rowNumber] = $row;
        }

        return $keyed;
    }

    /**
     * A cell as a trimmed string: whole floats lose the ".0", booleans become 1 / 0.
     */
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_float($value)) {
            return floor($value) === $value && abs($value) < 1e15
                ? (string) (int) $value
                : rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }
}
