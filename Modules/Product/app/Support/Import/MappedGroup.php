<?php

declare(strict_types=1);

namespace Modules\Product\Support\Import;

/**
 * The rows of one "Group ID" in a bulk catalog upload, mapped to a product: the
 * ProductData::fromArray() input (without seller and images), the image URLs to fetch,
 * and the problems found per spreadsheet row.
 */
final class MappedGroup
{
    /**
     * @param  list<int>  $rows  spreadsheet row numbers, the product row first; variant i is row $rows[i]
     * @param  array<string, mixed>  $data
     * @param  list<string>  $imageUrls
     * @param  array<int, list<array{column: string|null, message: string}>>  $errors
     * @param  array<int, list<array{column: string|null, message: string}>>  $warnings
     */
    public function __construct(
        public string $groupId,
        public array $rows,
        public array $data = [],
        public array $imageUrls = [],
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function addError(int $row, ?string $column, string $message): void
    {
        $this->errors[$row][] = ['column' => $column, 'message' => $message];
    }

    public function addWarning(int $row, ?string $column, string $message): void
    {
        $this->warnings[$row][] = ['column' => $column, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function firstRow(): int
    {
        return $this->rows[0];
    }

    /**
     * Spreadsheet row of a ProductValidationException key: "variants.2.price" is the third
     * variant's row; product-level keys belong to the group's first row.
     */
    public function rowForErrorKey(string $key): int
    {
        if (preg_match('/^variants\.(\d+)\./', $key, $match)) {
            return $this->rows[(int) $match[1]] ?? $this->firstRow();
        }

        return $this->firstRow();
    }

    /**
     * Template column of a ProductValidationException key.
     */
    public static function columnForErrorKey(string $key): ?string
    {
        if (preg_match('/^variants\.\d+\.(\w+)/', $key, $match)) {
            return $match[1] === 'options' ? null : $match[1];
        }

        if (preg_match('/^attributes\.(\d+)/', $key, $match)) {
            return 'attr_'.$match[1];
        }

        return match (true) {
            str_ends_with($key, '.name') && str_starts_with($key, 'translations.') => 'product_name',
            str_ends_with($key, '.description') => 'description',
            str_ends_with($key, '.highlights') => 'highlights',
            str_starts_with($key, 'images') => 'image_url_1',
            $key === 'catalog_brand_id' => 'brand',
            $key === 'manufacturer_details' => 'manufacturer_name',
            in_array($key, ['hsn_code', 'gst_percent', 'country_of_origin'], true) => $key,
            default => null,
        };
    }
}
