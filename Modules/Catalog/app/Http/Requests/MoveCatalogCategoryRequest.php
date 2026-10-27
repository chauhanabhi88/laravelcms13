<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Drag and drop on the admin tree: {id, parent_id (empty = top level), position}.
 */
class MoveCatalogCategoryRequest extends FormRequest
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
            'id' => ['required', 'integer', Rule::exists('catalog_category', 'id')->whereNull('deleted_at')],
            'parent_id' => ['nullable', 'integer', Rule::exists('catalog_category', 'id')->whereNull('deleted_at')],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
