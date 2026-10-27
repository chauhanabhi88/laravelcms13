<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $minPasswordLength = settings('customer', 'min_password_length') ?: 8;

        return [

            'password' => [
                'required',
                'confirmed',
                'min:'.$minPasswordLength,             // must be at least 10 characters in length
                'max:'.(settings('customer', 'max_password_length') ?: 20),
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:'.config('core.special_character_regex_server'), // must contain a special character
            ],
            // Accounts created by OTP have no password yet: they set one without a current password.
            'old_password' => auth('customer')->user()?->hasPassword() === false
                ? ['nullable']
                : ['required', 'current_password:customer'],
        ];
    }

    public function messages()
    {
        return [
            'password.min' => trans('customer::customer.messages.invalid_password', ['password_length' => settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.regex' => trans('customer::customer.messages.invalid_password', ['password_length' => settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.max' => trans('customer::customer.messages.invalid_password', ['password_length' => settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'old_password.current_password' => trans('customer::customer.messages.old_password_invalid'),
        ];
    }
}
