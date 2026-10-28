<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rule simulator input (plan M13 §6): route, weight, payment and optional seller/category.
 */
class SimulateCourierRuleRequest extends FormRequest
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
            'origin_pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
            'destination_pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
            'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
            'payment' => ['required', Rule::in(['cod', 'prepaid'])],
            'seller_id' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
