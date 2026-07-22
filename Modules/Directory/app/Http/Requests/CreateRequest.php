<?php

namespace Modules\Directory\Http\Requests;

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
            'country' => 'required',
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
            'currencySetup.*.required' => "The :attribute field is required.",
            'symbol.*.required' => "This :attribute field is required.",
            'rate.*.numeric' => 'The :attribute field allowed only numeric value.',
            'rate.*.not_in' => 'The :attribute field not allowed 0 value.'
        ];
    }
}
