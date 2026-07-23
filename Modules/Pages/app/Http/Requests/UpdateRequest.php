<?php

namespace Modules\Pages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Pages\Models\Pages;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $pages = new Pages;
        $rules = [];
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.title"] = 'required';
        }
        $rules['slug'] = 'required|unique:'.$pages->getTable().',slug,'.$this->id;

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
        return [
            // 'page.*.unique' => 'The slug has already been taken.',
        ];
    }
}
