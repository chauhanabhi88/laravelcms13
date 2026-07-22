<?php

namespace Modules\ImportReport\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCompetitorMappingRequest extends FormRequest
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
        
        return [
            "competitorMapping" => 'required|mimes:csv,xlsx',
        ];
    }

    public function messages()
    {
        return [
            'importreport.*.required' => trans("core::core.messages.required_field"),
        ];
    }
}
