<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Size chart grid: columns[] (measurement names) and rows[i][size] + rows[i][values][].
 */
class CatalogSizeChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'unit' => ['required', Rule::in((array) config('catalog.size_chart_units'))],
            'image' => ['nullable', 'image', 'mimes:'.config('catalog.upload.image_types'), 'max:'.config('catalog.upload.max_size_kb')],
            'status' => ['nullable', Rule::in([config('core.enabled'), config('core.disabled')])],
            'columns' => ['required', 'array', 'min:1', 'max:12'],
            'columns.*' => ['required', 'string', 'max:40', 'distinct:ignore_case'],
            'rows' => ['required', 'array', 'min:1', 'max:40'],
            'rows.*.size' => ['required', 'string', 'max:20', 'distinct:ignore_case'],
            'rows.*.values' => ['nullable', 'array'],
            'rows.*.values.*' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'columns.required' => trans('catalog::catalog_size_chart.messages.columns_required'),
            'rows.required' => trans('catalog::catalog_size_chart.messages.rows_required'),
            'rows.*.size.required' => trans('catalog::catalog_size_chart.messages.row_size_required'),
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $columnCount = count((array) $this->input('columns', []));

                foreach ((array) $this->input('rows', []) as $index => $row) {
                    $values = array_filter((array) ($row['values'] ?? []), fn ($value): bool => trim((string) $value) !== '');

                    if (count($values) !== $columnCount) {
                        $validator->errors()->add("rows.{$index}.values", trans('catalog::catalog_size_chart.messages.row_values'));
                    }
                }
            },
        ];
    }

    /**
     * The grid as stored: trimmed names and one value per column, rows in submitted order.
     *
     * @return array{columns: list<string>, rows: list<array{size: string, values: list<string>}>}
     */
    public function grid(): array
    {
        $columns = array_values(array_map(fn ($column): string => trim((string) $column), (array) $this->validated('columns')));

        $rows = array_values(array_map(fn (array $row): array => [
            'size' => trim((string) $row['size']),
            'values' => array_values(array_map(fn ($value): string => trim((string) $value), array_slice(array_values((array) ($row['values'] ?? [])), 0, count($columns)))),
        ], (array) $this->validated('rows')));

        return ['columns' => $columns, 'rows' => $rows];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
            'columns' => array_values((array) $this->input('columns', [])),
            'rows' => array_values((array) $this->input('rows', [])),
        ]);
    }
}
