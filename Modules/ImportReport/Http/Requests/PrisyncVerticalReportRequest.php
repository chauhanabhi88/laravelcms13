<?php

namespace Modules\ImportReport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrisyncVerticalReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        //$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));
        
        return [
            "competitorMapping" => 'required|mimes:csv,xlsx',
        ];
    }

    public function messages()
    {
        return [
            'importreport.*.required' => trans("core::core.messages.required_field"),
            //'importreport.*.max' => trans("core::core.messages.max-size"),
        ];
    }
}
