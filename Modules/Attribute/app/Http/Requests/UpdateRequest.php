<?php

namespace Modules\Attribute\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Attribute\Models\Attribute;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];
        $attribute = new Attribute;
        $attributecode = 'required|unique:'.$attribute->getTable().',code,'.$this->id;
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.name"] = 'required';
        }
        $rules['code'] = $attributecode;
        $rules['is_required'] = 'required';

        $imageTypes = ! empty(settings('attribute', 'image_type')) ? settings('attribute', 'image_type') : 'jpeg,jpg,png';
        $maxUpload = $this->getMaxUpload();
        $maxUpload *= 1024;
        $rules['option.old.*.image'] = 'mimes:'.$imageTypes.'|max:'.$maxUpload;
        $rules['option.new.*.image'] = 'mimes:'.$imageTypes.'|max:'.$maxUpload;

        return $rules;
    }

    private function getMaxUpload()
    {
        $maxUploadSize = settings('attribute', 'max_upload_size');
        $maxUploadServer = (int) (ini_get('upload_max_filesize')) > (int) (ini_get('post_max_size')) ? (int) (ini_get('post_max_size')) : (int) (ini_get('upload_max_filesize'));

        return $maxUploadSize ? ($maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize) : $maxUploadServer;
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
