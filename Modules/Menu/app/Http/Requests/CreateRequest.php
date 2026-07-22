<?php

namespace Modules\Menu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Menu\Models\Menu;
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
        $module = new Menu();
        return [
            'menu.label' => 'required|max:255'
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
            'menu.*.required' => trans("core::core.messages.required_field"),
        ];
    }
}
