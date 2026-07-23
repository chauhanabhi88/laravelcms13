<?php

namespace Modules\Contact\Http\Requests;

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
        return [
            'contact.name' => 'required|string|max:255',
            'contact.email' => 'required|email|max:255',
            'contact.contact_number' => 'nullable|string|max:20',
            'contact.content' => 'nullable|string',
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
            'contact.*.required' => trans('contact::contact.messages.required'),
            'contact.email.email' => trans('contact::contact.messages.invalid_email'),
        ];
    }
}
