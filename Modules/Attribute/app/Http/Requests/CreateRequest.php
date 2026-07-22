<?php

namespace Modules\Attribute\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Attribute\Models\Attribute;


class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];
        $attribute = new Attribute();
        $attributecode = 'required|unique:'.$attribute->getTable().',code';
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.name"] = 'required';
        }
        $rules['code'] = $attributecode;
        $rules['input_type'] = 'required';
        $rules['is_required'] = 'required';
        return $rules;
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
        return [];
    }
}
