<?php
namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Customer\Models\Customer;

class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $minPasswordLength = settings('customer', 'min_password_length');
        $customer = new Customer();
        return [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'email' => 'required|email|unique:'.$customer->getTable().',email,NULL,id,deleted_at,NULL',
            'password' => [
                'required',
                'confirmed',
                'min:' . $minPasswordLength,             // must be at least 10 characters in length
                'max:' .  settings('customer', 'max_password_length'),
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:' . config('core.special_character_regex_server'), // must contain a special character
            ],
            'contact_number' => 'required|numeric',
        ];
    }

    public function messages()
    {
        return [
            'customer.*.required' => trans("customer::customer.messages.required"),
            'customer.email.email' => trans("customer::customer.messages.invalid_email"),
            'customer.email.unique' => trans("customer::customer.messages.email_unique"),
            'password.min' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.regex' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.max' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
        ];
    }
}
