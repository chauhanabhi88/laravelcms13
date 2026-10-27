<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Seller\Support\CurrentSeller;
use Modules\Seller\Support\Validation\Pan;

/**
 * Onboarding step 1: business details.
 */
class BusinessDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pan' => strtoupper(trim((string) $this->input('pan'))),
            'store_name' => trim((string) $this->input('store_name')),
            'legal_name' => trim((string) $this->input('legal_name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sellerId = app(CurrentSeller::class)->id();

        return [
            'store_name' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[\pL\pN][\pL\pN &\'.\-]*$/u', Rule::unique('seller', 'store_name')->ignore($sellerId)],
            'legal_name' => ['required', 'string', 'min:3', 'max:200'],
            'business_type' => ['required', Rule::in((array) config('seller.business_types'))],
            'pan' => ['required', 'string', 'size:10', new Pan],
            'email' => ['required', 'email', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_name.unique' => trans('seller::validation.store_name.taken'),
            'store_name.regex' => trans('seller::validation.store_name.format'),
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
