<?php

declare(strict_types=1);

namespace Modules\Storefront\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Storefront\Support\DeliveryPincode;

/**
 * POST /delivery-check and /api/v1/delivery-check: {product_id, variant_id?, pincode}.
 */
class DeliveryCheckRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'pincode' => ['required', 'string', 'regex:'.DeliveryPincode::PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pincode.required' => trans('storefront::product.delivery.invalid_pincode'),
            'pincode.regex' => trans('storefront::product.delivery.invalid_pincode'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('pincode'))) {
            $this->merge(['pincode' => preg_replace('/\s+/', '', $this->input('pincode'))]);
        }
    }
}
