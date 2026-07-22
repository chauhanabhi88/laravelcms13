<?php

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Blog\Models\BlogPost;
use Illuminate\Http\Request;

class CreateBlogPostRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
       $module = new BlogPost();
          $rules = [];
			foreach (getLanguageOptions() as $locale => $value) {
				$rules["{$locale}.title"] = "required";
				$rules["{$locale}.content"] = "required";
				$rules["{$locale}.meta_keywords"] = "required";
				$rules["{$locale}.meta_description"] = "required";
}
				$rules["slug"] = "required";
				$rules["image"] = [
					"mimes:" . $this->getImageType() , "max:" . $this->getMaxUpload(), "dimensions:min_width=" . (!empty(settings("blog_post", "min_upload_width")))?settings("blog_post", "min_upload_width"):"100" , ",min_height=" . (!empty(settings("blog_post", "min_upload_height")))?settings("blog_post", "min_upload_height"):"100",
					function($attribute, $value, $fail) {
						 $temp  = (!empty(settings("blog_post", "image_ratio")))?settings("blog_post", "image_ratio"):"1";
						$ratio = (float)$temp;
						$origRatio = $this->getImageRatio();
						 if ($origRatio != $ratio) {
							 return $fail(trans("core::core.messages.invalid_image_ratio"));
						}
					}
				];
				$rules["author"] = "required";
				$rules["post_date"] = "required";

            return $rules;
    }

	public function getImageRatio() {
		 $image_info = getimagesize(Request::file("image")->getRealPath());
		$value = round(($image_info[0]/$image_info[1]), 2);
		return $value;
	}

	private function getMaxUpload() {
	$maxUploadSize = (!empty(settings("blog", "max_upload_size"))) ? settings("blog", "max_upload_size") : "1";
		$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
		 $maxUpload = $maxUploadSize > $maxUploadServer ? $maxUploadServer : $maxUploadSize;
		return ($maxUpload * 1024);
	}

	private function getImageType() {
		return (!empty(settings("blog", "image_type"))) ? settings("blog", "image_type") : "jpg,jpeg,png" ;
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
			$rules["image.mimes"] = trans("core::core.validation-message.image.file-type", ["file_type" => $this->getImageType()]); 
			$rules["image.max"] = trans("core::core.validation-message.image.max-size", ["size" => ($this->getMaxUpload() / 1024)]);
			$rules["image.dimensions"] = trans("core::core.messages.invalid_dimension");

        return $rules;
    }
}
