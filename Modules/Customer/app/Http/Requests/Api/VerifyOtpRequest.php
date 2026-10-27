<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Requests\Api;

use Illuminate\Validation\Rule;
use Modules\Customer\Http\Requests\SendOtpRequest;
use Modules\Customer\Models\CustomerDeviceToken;

/**
 * Mobile API code check (no session): the identifier travels with the code, plus the device
 * the token is issued for.
 */
class VerifyOtpRequest extends SendOtpRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (is_string($this->input('code'))) {
            $this->merge(['code' => preg_replace('/\s+/', '', $this->input('code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'code' => ['required', 'digits:'.config('core.otp.length', 6)],
            'device_name' => ['required', 'string', 'max:191'],
            'device_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', Rule::in(array_values((array) config('customer.device_type')))],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'code.required' => trans('customer::customer_front.otp.code_required'),
            'code.digits' => trans('customer::customer_front.otp.code_digits', ['length' => config('core.otp.length', 6)]),
        ]);
    }

    /**
     * @return array{device_token: string, device_type: string|null}|null
     */
    public function device(): ?array
    {
        $token = $this->validated('device_token');

        return filled($token) && class_exists(CustomerDeviceToken::class)
            ? ['device_token' => (string) $token, 'device_type' => $this->validated('device_type')]
            : null;
    }
}
