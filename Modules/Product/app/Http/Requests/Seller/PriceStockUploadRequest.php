<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Price & stock upload: Seller SKU, MRP, Price, Stock (xlsx / csv).
 */
class PriceStockUploadRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:'.config('product.bulk_mimes', 'xlsx,csv,txt'), 'max:'.((int) config('product.bulk_max_mb', 10) * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['file' => trans('product::seller.uploads.file')];
    }
}
