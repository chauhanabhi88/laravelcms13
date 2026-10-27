<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Seller\Support\CurrentSeller;
use Modules\Seller\Support\SellerSettings;
use Modules\Seller\Support\Validation\Gstin;

/**
 * Onboarding step 2: GSTIN (with certificate) or, when allowed, a non-GST enrolment id.
 */
class TaxDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'has_gst' => SellerSettings::allowsNonGstSellers() ? (int) $this->input('has_gst', config('core.yes')) : (int) config('core.yes'),
            'gstin' => strtoupper(trim((string) $this->input('gstin'))),
            'enrolment_id' => strtoupper(trim((string) $this->input('enrolment_id'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $seller = app(CurrentSeller::class)->seller();
        $hasCertificate = $seller?->kycDocuments()->where('type', 'gst_certificate')->exists() ?? false;
        $mimes = (string) config('seller.kyc_mimes');
        $maxKb = (int) config('seller.kyc_max_file_mb') * 1024;

        return [
            'has_gst' => ['required', Rule::in([(int) config('core.yes'), (int) config('core.no')])],
            'gstin' => [
                'exclude_unless:has_gst,'.config('core.yes'),
                'required',
                'string',
                'size:15',
                new Gstin($seller?->pan),
                Rule::unique('seller', 'gstin')->ignore($seller?->id),
            ],
            'gst_certificate' => [
                'exclude_unless:has_gst,'.config('core.yes'),
                $hasCertificate ? 'nullable' : 'required',
                'file',
                'mimes:'.$mimes,
                'max:'.$maxKb,
            ],
            'enrolment_id' => [
                'exclude_unless:has_gst,'.config('core.no'),
                'required',
                'string',
                'regex:/^[A-Z0-9]{8,20}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gstin.unique' => trans('seller::validation.gstin.taken'),
            'enrolment_id.regex' => trans('seller::validation.enrolment_id'),
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
