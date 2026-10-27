<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Customer\Models\Customer;
use Modules\Customer\Support\PhoneNumber;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $customer = new Customer;
        $maxUpload = $this->getMaxUpload();
        $maxUpload *= 1024;
        $imageTypes = ! empty(settings('customer', 'image_type')) ? settings('customer', 'image_type') : 'jpeg,jpg,png';
        $minPasswordLength = ! empty(settings('customer', 'min_password_length')) ? settings('customer', 'min_password_length') : 6;
        $passwordRules = [];
        if ($this->password) {
            $passwordRules = [
                'confirmed',
                'min:'.$minPasswordLength,             // must be at least 10 characters in length
                'max:'.(! empty(settings('customer', 'max_password_length')) ? settings('customer', 'max_password_length') : '20'),
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:'.config('core.special_character_regex_server'), // must contain a special character
            ];
        }

        // M08: OTP accounts may have no email or last name; email is required only without a mobile.
        return [
            'customer.first_name' => 'required|max:255',
            'customer.last_name' => 'nullable|max:255',
            'customer.email' => [
                Rule::requiredIf(fn () => $this->has('customer.phone')
                    ? blank($this->input('customer.phone'))
                    : blank(Customer::query()->whereKey((int) $this->id)->value('phone'))),
                'nullable',
                'email',
                'unique:'.$customer->getTable().',email,'.$this->id.',id,deleted_at,NULL',
            ],
            'password' => $passwordRules,
            'profile_picture' => 'mimes:'.$imageTypes.'|max:'.$maxUpload,
            'customer.cpassword' => 'min:'.$minPasswordLength,
            // 'customer.status' => 'required|integer',
            'customer.contact_number' => 'nullable|numeric',
            'customer.phone' => ['nullable', 'string', 'max:20'],
            'customer.is_reseller' => ['nullable', Rule::in([config('core.yes'), config('core.no')])],
            'customer.reseller_business_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * The mobile must be a valid Indian number that no other customer (deleted ones included) uses.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $raw = $this->input('customer.phone');

                if (blank($raw) || $validator->errors()->has('customer.phone')) {
                    return;
                }

                $phone = PhoneNumber::normalise($raw);

                if ($phone === null) {
                    $validator->errors()->add('customer.phone', trans('customer::customer.messages.phone_invalid'));
                } elseif (Customer::withTrashed()->where('phone', $phone)->whereKeyNot((int) $this->id)->exists()) {
                    $validator->errors()->add('customer.phone', trans('customer::customer.messages.phone_unique'));
                }
            },
        ];
    }

    private function getMaxUpload()
    {
        $maxUploadSize = settings('customer', 'max_upload_size');
        $maxUploadServer = (int) (ini_get('upload_max_filesize')) > (int) (ini_get('post_max_size')) ? (int) (ini_get('post_max_size')) : (int) (ini_get('upload_max_filesize'));

        $maxUpload = $maxUploadSize ? $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize : $maxUploadServer;

        // $maxUpload *= 1024;
        return $maxUpload;
    }

    /**
     * Determine if the customer is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    public function messages()
    {
        return [
            'customer.*.required' => trans('customer::customer.messages.required'),
            'customer.email.email' => trans('customer::customer.messages.invalid_email'),
            'customer.email.unique' => trans('customer::customer.messages.email_unique'),
            'customer.status.integer' => trans('customer::customer.messages.invalid_status'),
            'password.min' => trans('customer::customer.messages.invalid_password', ['password_length' => settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.regex' => trans('customer::customer.messages.invalid_password', ['password_length' => settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.max' => trans('customer::customer.messages.invalid_password', ['password_length' => settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
        ];
    }
}
