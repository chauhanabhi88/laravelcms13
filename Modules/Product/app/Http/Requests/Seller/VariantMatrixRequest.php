<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

/**
 * Wizard step 3: the variant matrix as JSON (POST seller.listing.variants.save). Rows are in
 * matrix order, so "variants.N.*" errors from here and from ProductFactoryService point at
 * the same row.
 */
class VariantMatrixRequest extends SellerProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['required', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];

        return [
            'variants' => ['required', 'array', 'min:1', 'max:'.(int) config('product.max_variants', 100)],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.options' => ['present', 'array'],
            'variants.*.options.*' => ['integer'],
            'variants.*.seller_sku' => ['required', 'string', 'max:64'],
            'variants.*.mrp' => $money,
            'variants.*.price' => $money,
            'variants.*.stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variants.*.weight_grams' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variants.max' => trans('product::seller.wizard.variants.too_many', ['max' => config('product.max_variants', 100)]),
            'variants.required' => trans('product::seller.wizard.variants.none'),
            'variants.min' => trans('product::seller.wizard.variants.none'),
            'variants.*.mrp.regex' => trans('product::seller.uploads.row_errors.money'),
            'variants.*.price.regex' => trans('product::seller.uploads.row_errors.money'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'variants.*.seller_sku' => trans('product::seller.wizard.columns.seller_sku'),
            'variants.*.mrp' => trans('product::seller.wizard.columns.mrp'),
            'variants.*.price' => trans('product::seller.wizard.columns.price'),
            'variants.*.stock' => trans('product::seller.wizard.columns.stock'),
            'variants.*.weight_grams' => trans('product::seller.wizard.columns.weight_grams'),
        ];
    }
}
