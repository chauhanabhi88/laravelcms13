<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Seller\Support\Phone;

/**
 * Settings › Business. Verified KYC fields (legal name, PAN, GSTIN) are read-only here;
 * changes go through support.
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone_e164' => Phone::normalise($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'phone_e164' => ['required', 'string'],
            'auto_accept_orders' => ['nullable', Rule::in([(int) config('core.yes'), (int) config('core.no')])],
            'logo' => ['nullable', 'image', 'mimes:'.config('seller.logo_mimes'), 'max:'.(int) config('seller.logo_max_kb'), 'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
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

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('seller::onboarding.attributes');
    }
}
