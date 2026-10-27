<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Seller\Support\Phone;

/**
 * Admin edit of a seller's display and contact details. Status changes have their own
 * actions (approve, suspend…) so they go through the state machine.
 */
class SellerUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_e164' => Phone::normalise($this->input('phone')),
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'store_name' => ['required', 'string', 'min:3', 'max:80', Rule::unique('seller', 'store_name')->ignore($id)],
            'legal_name' => ['required', 'string', 'min:3', 'max:200'],
            'business_type' => ['required', Rule::in((array) config('seller.business_types'))],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'phone_e164' => ['required', 'string'],
            'auto_accept_orders' => ['nullable', Rule::in([(int) config('core.yes'), (int) config('core.no')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_e164.required' => trans('seller::validation.phone.invalid'),
        ];
    }
}
