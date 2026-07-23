<?php

namespace Modules\Language\Http\Requests;

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
        return [
            'language.title' => 'required',
            'language.locale' => 'required',
            'language.is_default' => 'required|in:'.config('core.yes').','.config('core.no'),
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
            'language.*.required' => trans('language::language.messages.required'),
        ];
    }
}
