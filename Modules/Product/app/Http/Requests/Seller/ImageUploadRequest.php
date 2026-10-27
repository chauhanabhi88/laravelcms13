<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

/**
 * Wizard step 4: one image for the shared set or a colour's set (attribute_option_id).
 * ProductImageService repeats the checks and adds the 500 px minimum.
 */
class ImageUploadRequest extends SellerProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:'.config('product.upload.image_types'), 'max:'.(int) config('product.upload.max_size_kb')],
            'attribute_option_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => trans('product::seller.wizard.images.image')];
    }
}
