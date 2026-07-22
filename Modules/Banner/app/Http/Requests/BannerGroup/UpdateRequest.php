<?php

namespace Modules\Banner\Http\Requests\BannerGroup;

use Modules\Banner\Models\BannerGroup;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {   
        $bannerGroup = new BannerGroup();
        $rules = [];
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.name"] = 'required';
        }
        $rules['code'] = 'required|unique:'.$bannerGroup->getTable().',code,'.$this->id;
        $rules['sort_order'] = 'required|numeric|min:0';
        // $rules['status'] = 'required';

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
