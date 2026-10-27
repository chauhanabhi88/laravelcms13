<?php

namespace Modules\Contact\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same rules as the API endpoint (Api\V1\ContactController@save), plus a
     * honeypot ("website") that real users never see or fill in.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_number' => ['nullable', 'regex:/^[0-9]{10}$/'],
            'content' => ['required', 'string', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => trans('contact::contact_front.labels.name'),
            'email' => trans('contact::contact_front.labels.email'),
            'contact_number' => trans('contact::contact_front.labels.contact_number'),
            'content' => trans('contact::contact_front.labels.enter_question'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_number.regex' => trans('contact::contact_front.messages.number_valid'),
        ];
    }
}
