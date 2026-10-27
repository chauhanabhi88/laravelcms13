<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The 6-digit login code. The web flow reads the identifier from the session; the API sends
 * "phone" (plus device details) in the same request.
 */
class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('code'))) {
            $this->merge(['code' => implode('', $this->input('code'))]);
        }

        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', $this->input('code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:'.config('core.otp.length', 6)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => trans('customer::customer_front.otp.code_required'),
            'code.digits' => trans('customer::customer_front.otp.code_digits', ['length' => config('core.otp.length', 6)]),
        ];
    }
}
