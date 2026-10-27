<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Onboarding step 5: accept the current supplier agreement and submit.
 */
class AgreementRequest extends FormRequest
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
            'accept_agreement' => ['accepted'],
            'agreement_version' => ['required', 'in:'.config('seller.agreement_version')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accept_agreement.accepted' => trans('seller::validation.accept_agreement'),
            'agreement_version.in' => trans('seller::validation.agreement_outdated'),
        ];
    }
}
