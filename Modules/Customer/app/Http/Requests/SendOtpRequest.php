<?php

declare(strict_types=1);

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Core\Services\Otp\OtpService;
use Modules\Customer\Support\CustomerSettings;
use Modules\Customer\Support\PhoneNumber;

/**
 * Login code request: channel "sms" (default, Indian mobile) or "email" when email OTP is enabled.
 * The web form posts "phone" or "email"; the API posts "phone".
 */
class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['channel' => $this->input('channel') ?: OtpService::CHANNEL_SMS]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $channels = CustomerSettings::emailOtpEnabled()
            ? [OtpService::CHANNEL_SMS, OtpService::CHANNEL_EMAIL]
            : [OtpService::CHANNEL_SMS];

        return [
            'channel' => ['required', Rule::in($channels)],
            'phone' => ['required_if:channel,'.OtpService::CHANNEL_SMS, 'nullable', 'string', 'max:20'],
            'email' => ['required_if:channel,'.OtpService::CHANNEL_EMAIL, 'nullable', 'string', 'email', 'max:191'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('channel') === OtpService::CHANNEL_SMS && filled($this->input('phone')) && ! PhoneNumber::isValid($this->input('phone'))) {
                    $validator->errors()->add('phone', trans('customer::customer_front.otp.invalid_phone'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required_if' => trans('customer::customer_front.otp.phone_required'),
            'email.required_if' => trans('customer::customer_front.otp.email_required'),
            'email.email' => trans('customer::customer_front.otp.invalid_email'),
            'channel.in' => trans('customer::customer_front.otp.channel_unavailable'),
        ];
    }

    public function channel(): string
    {
        return (string) $this->validated('channel');
    }

    public function identifier(): string
    {
        return $this->channel() === OtpService::CHANNEL_EMAIL
            ? strtolower(trim((string) $this->validated('email')))
            : (string) PhoneNumber::normalise($this->validated('phone'));
    }
}
