<?php

namespace Modules\Cron\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Cron\Models\Cron;

class CreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $cron = new Cron();
        return [
            'cron.title' => 'required',
            'cron.command' => 'required|unique:'.$cron->getTable().',command',
            'cron.description' => 'required',
            // 'cron.status' => 'required',
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
            'cron.*.required' => trans("cron::cron.messages.required"),
            'cron.command.unique' => trans("cron::cron.messages.command_unique"),
        ];
    }
}
