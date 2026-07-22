<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Customer\Models\Customer;

class ProfileRequest extends FormRequest
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
        $imageTypes = settings('customer', 'image_type');
        $minUploadSize = settings('customer', 'min_upload_size');
        $minUploadSize = 1024 * $minUploadSize;
        return [
            'first_name' => 'required|max:255',
            'last_name' => 'required|max:255',
            'email' => [
                'required',
                'email',
                'unique:'.$customer->getTable().',email,'.decrypt_It($this->id).',id,deleted_at,NULL'
            ],
            'profile_picture' => 'mimes:'.$imageTypes.'|max:'.$maxUpload.'|min:'.$minUploadSize,
            'contact_number' => 'required|numeric'
        ];
    }
    private function getMaxUpload(){
        $maxUploadSize = settings('transport', 'max_upload_size');
        $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
        $maxUpload = $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize;
        return $maxUpload;
    }
     /**
     * Determine if the customer is authorized to make this request.
     *
     * @return bool
     */
    public function messages()
    {
        return [
            '*.required' => trans("customer::customer.messages.required"),
            'email.email' => trans("customer::customer.messages.invalid_email"),
            'email.unique' => trans("customer::customer.messages.email_unique"),
        ];
    }

}
