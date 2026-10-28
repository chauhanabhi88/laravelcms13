<?php

declare(strict_types=1);

namespace Modules\Cart\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Cart\Support\CartSettings;

/**
 * {variant_id, quantity} for Add to Cart and Buy Now (web and API). Quantity defaults to 1.
 */
class AddToCartRequest extends FormRequest
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
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartSettings::maxQtyPerLine()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variant_id.required' => trans('cart::front.errors.choose_size'),
            'quantity.max' => trans('cart::front.errors.quantity', ['max' => CartSettings::maxQtyPerLine()]),
        ];
    }

    public function variantId(): int
    {
        return (int) $this->validated('variant_id');
    }

    public function quantity(): int
    {
        return (int) ($this->validated('quantity') ?? 1);
    }
}
