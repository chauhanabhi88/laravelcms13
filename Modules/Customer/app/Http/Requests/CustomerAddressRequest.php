<?php

namespace Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAddressRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'address.street_name' => 'required',
            'address.building'    =>  'required|max:255',
            'address.unit_no'   =>  'required|max:255',
            'address.postal_code'   =>  'required|numeric',
            'address.tag'   =>  'required|max:255'
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
