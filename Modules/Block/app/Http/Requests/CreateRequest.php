<?php

namespace Modules\Block\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Block\Models\Block;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $block = new Block;
        $rules = [];
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.title"] = 'required';
        }
        $rules['slug'] = ['required', Rule::unique($block->getTable(), 'slug')->whereNull('deleted_at')];

        // $rules['is_enabled'] = 'required';
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
