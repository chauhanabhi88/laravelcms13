<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

/**
 * Wizard step 4: remove one image ("path") or put the images in a new order ("paths").
 */
class ImageChangeRequest extends SellerProductRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'path' => ['required_without:paths', 'nullable', 'string', 'max:255'],
            'paths' => ['required_without:path', 'nullable', 'array', 'max:200'],
            'paths.*' => ['string', 'max:255'],
        ];
    }
}
