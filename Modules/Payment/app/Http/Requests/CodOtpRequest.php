<?php

declare(strict_types=1);

namespace Modules\Payment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The COD OTP: one string ("code") or the per-digit boxes (code[]) joined.
 */
class CodOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_array($code)) {
            $this->merge(['code' => implode('', array_map(fn (mixed $digit): string => is_scalar($digit) ? (string) $digit : '', $code))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $length = (int) config('core.otp.length', 6);

        return [
            'code' => ['required', 'string', 'digits:'.$length],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => trans('payment::front.cod_otp.required'),
            'code.digits' => trans('payment::front.cod_otp.invalid'),
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
