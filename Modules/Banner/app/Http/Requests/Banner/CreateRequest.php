<?php

namespace Modules\Banner\Http\Requests\Banner;

use Modules\Banner\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $banner = new Banner();
        $rules = [];
        $maxUpload = $this->getMaxUpload();
        $maxUpload *= 1024;
        $imageTypes = (!empty(settings('banner', 'image_type'))) ? settings('banner', 'image_type') : 'jpeg,jpg,png';
        foreach (getLanguageOptions() as $locale => $value) {
            $rules["{$locale}.title"] = 'required';
            $rules["{$locale}.content"] = 'required';
        }
        $rules['code'] = 'required|unique:'.$banner->getTable().',code';
        $rules['image'] = 'mimes:'.$imageTypes.'|required|max:'.$maxUpload;
        $rules['group_id'] = 'required';
        $rules['sort_order'] = 'required|numeric|min:0';
        
        return $rules;
    }

    private function getMaxUpload(){
        $maxUploadSize = (!empty(settings('banner', 'max_upload_size')))?settings('banner', 'max_upload_size'):config('asgard.banner.config.defualt_image_size');
        $maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
        $maxUpload = $maxUploadSize ? $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize : $maxUploadServer;
        return ($maxUpload * 1024);
    }


    private function getImageType(){
        return (!empty(settings('banner', 'image_type')))?settings('banner', 'image_type'):config('asgard.banner.config.defualt_image_type');
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
        $messages = [];
            $messages['image.mimes'] = trans("core::core.validation-message.image.file-type",['file_type'=>$this->getImageType()]);
            $messages['image.max'] = trans("core::core.validation-message.image.max-size",['size'=>($this->getMaxUpload()/1024)]) ;
            $messages['image.required'] = trans("core::core.validation-message.required",['field'=>'image']);
        return $messages;
    }
}
