<?php

namespace Modules\Role\Http\Requests;

use Modules\Role\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpsertRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $role = new Role();
        return [
            'role.name' => 'required',
            'role.slug' => 'required|unique:'.$role->getTable().',slug,'.$this->id,
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
            'role.*.required' => trans("role::role.messages.required"),
            'role.*.unique' => trans("role::role.messages.slug_validation"),
        ];
    }
}
