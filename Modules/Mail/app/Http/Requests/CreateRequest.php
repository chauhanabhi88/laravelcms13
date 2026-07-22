<?php

namespace Modules\Mail\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Mail\Models\MailTemplate;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $mail = new MailTemplate();
        return [
            'mail.name' => 'required',
            'mail.subject' => 'required',
            'mail.slug' => 'required|unique:'.$mail->getTable().',slug',
            'mail.body' => 'required',
            // 'mail.status' => 'required',
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
            'mail.*.unique' => trans("mail::mail.messages.slug_unique"),
        ];
    }
}
