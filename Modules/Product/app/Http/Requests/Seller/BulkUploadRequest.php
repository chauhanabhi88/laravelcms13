<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * New catalog upload: the filled category template (xlsx / csv).
 */
class BulkUploadRequest extends FormRequest
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
            'catalog_category_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'mimes:'.config('product.bulk_mimes', 'xlsx,csv,txt'), 'max:'.((int) config('product.bulk_max_mb', 10) * 1024)],
            'save_as_draft' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'catalog_category_id' => trans('product::seller.uploads.category'),
            'file' => trans('product::seller.uploads.file'),
        ];
    }
}
