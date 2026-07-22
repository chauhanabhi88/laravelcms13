<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Customer\Models\Customer;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $customer = new Customer();
        $maxUpload = $this->getMaxUpload();
        $maxUpload *= 1024;
        $imageTypes = !empty(settings('customer', 'image_type')) ? settings('customer', 'image_type') : 'jpeg,jpg,png';
        $minPasswordLength = !empty(settings('customer', 'min_password_length')) ? settings('customer', 'min_password_length') : 6;
        return [
            'customer.first_name' => 'required|max:255',
            'customer.last_name' => 'required|max:255',
            'customer.email' => 'required|email|unique:'.$customer->getTable().',email,NULL,id,deleted_at,NULL',
            'password' => [
                'required',
                'confirmed',
                'min:' . $minPasswordLength,             // must be at least 10 characters in length
                'max:' . (!empty(settings('customer', 'max_password_length')) ? settings('customer', 'max_password_length') : '20'),
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                'regex:' . config('core.special_character_regex_server'), // must contain a special character
            ],
            'profile_picture' => 'mimes:'.$imageTypes.'|max:'.$maxUpload,
            //'customer.status' => 'integer',
            'customer.contact_number'   => 'required|numeric',
            'address.street_name'   => 'required',
            'address.building'      =>  'required|max:255',
            'address.unit_no'       =>  'required|max:255',
            'address.tag'           =>  'required|max:255',
            'address.postal_code'   =>  'required|numeric'
        ];
    }

    private function getMaxUpload(){
        $maxUploadSize = settings('customer', 'max_upload_size');
        $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));

        $maxUpload = $maxUploadSize ? $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize : $maxUploadServer;
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
            'customer.*.required' => trans("customer::customer.messages.required"),
            'customer.email.email' => trans("customer::customer.messages.invalid_email"),
            'customer.email.unique' => trans("customer::customer.messages.email_unique"),
            'customer.status.integer' => trans("customer::customer.messages.invalid_status"),
            'password.min' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.regex' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'password.max' => trans('customer::customer.messages.invalid_password',['password_length'=>settings('customer', 'min_password_length'), 'max_password_length' => settings('customer', 'max_password_length')]),
            'address.*.required'    =>  trans("customer::customer.messages.required")
        ];
    }
}
