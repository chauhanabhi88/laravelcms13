<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Seller\Support\Phone;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:191', Rule::unique('seller_user', 'email')],
            'phone' => ['required', 'string', 'max:20'],
            'phone_e164' => ['required', 'string', Rule::unique('seller_user', 'phone')],
            'password' => ['required', 'confirmed', static::passwordRule()],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_e164.required' => trans('seller::validation.phone.invalid'),
            'phone_e164.unique' => trans('seller::validation.phone.taken'),
            'email.unique' => trans('seller::validation.email.taken'),
            'accept_terms.accepted' => trans('seller::validation.accept_terms'),
        ];
    }

    public static function passwordRule(): Password
    {
        return Password::min(8)->max(64)->mixedCase()->numbers()->symbols();
    }
}
