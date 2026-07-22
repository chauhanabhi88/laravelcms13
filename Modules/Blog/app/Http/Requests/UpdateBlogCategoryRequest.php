<?php

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Blog\Models\BlogCategory;

class UpdateBlogCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $module = new BlogCategory();
          $rules = [];
			foreach (getLanguageOptions() as $locale => $value) {
				$rules["{$locale}.title"] = "required";
				$rules["{$locale}.description"] = "required";
				$rules["{$locale}.meta_keywords"] = "required";
				$rules["{$locale}.meta_description"] = "required";
            }
				$rules["slug"] = "required";
				$rules["sort_order"] = "required";

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
        $rules = [];
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.*.required"] =  trans("core::core.messages.required_field");
        }

        return $rules;
    }
}
