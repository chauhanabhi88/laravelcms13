<?php

namespace Modules\Column\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Column\Models\Column;
use Illuminate\Http\Request;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $module = new Column();
        return [

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

    public function messages()
    {
        return [


            'column.*.required' => trans("core::core.messages.required_field"),
        ];
    }
}
