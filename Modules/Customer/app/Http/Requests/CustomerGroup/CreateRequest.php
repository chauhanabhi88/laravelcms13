<?php

namespace Modules\Customer\Http\Requests\CustomerGroup;

use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(){

        $rules = [];
        $rules['name'] = 'required|max:255';
        return $rules;
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
        ];
    }
}
