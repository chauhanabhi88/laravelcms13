<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

use Illuminate\Validation\Rule;

/**
 * Wizard step 5: per-variant price, stock, SKU, weight, dimensions and threshold, in the
 * order the pricing table lists the draft's variants.
 */
class PricingStepRequest extends SellerProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];
        $dimension = ['nullable', 'numeric', 'gt:0', 'max:9999.9'];

        return [
            'variants' => ['required', 'array', 'max:'.(int) config('product.max_variants', 100)],
            'variants.*.seller_sku' => ['required', 'string', 'max:64'],
            'variants.*.mrp' => $money,
            'variants.*.price' => $money,
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.weight_grams' => ['required', 'integer', 'min:1', 'max:100000'],
            'variants.*.length_cm' => $dimension,
            'variants.*.breadth_cm' => $dimension,
            'variants.*.height_cm' => $dimension,
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variants.*.status' => ['nullable', Rule::in([(string) config('core.enabled'), (string) config('core.disabled')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.*.mrp.regex' => trans('product::seller.uploads.row_errors.money'),
            'variants.*.price.regex' => trans('product::seller.uploads.row_errors.money'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach (['seller_sku', 'mrp', 'price', 'stock', 'weight_grams', 'length_cm', 'breadth_cm', 'height_cm', 'low_stock_threshold'] as $field) {
            $attributes['variants.*.'.$field] = trans('product::seller.wizard.columns.'.$field);
        }

        return $attributes;
    }
}
