<?php

namespace Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CityImportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $fileTypes = settings('directory', 'import_country_type');
        return [
            'city_import_file' => 'required|mimes:'.$fileTypes,
        ];
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
        ];
    }
}
