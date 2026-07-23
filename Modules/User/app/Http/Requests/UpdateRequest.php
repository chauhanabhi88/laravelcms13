<?php

namespace Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\User\Models\User;

class UpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $user = new User;
        $rules = [];
        $rules['user.name'] = 'required';
        $rules['user.email'] = [
            'required',
            'email',
            'unique:'.$user->getTable().',email,'.$this->id.',id,deleted_at,NULL',
        ];
        /* the edit and profile forms post the password at the root of the payload, not under user[] */
        if ($this->input('password')) {
            $rules['password'] = [
                'confirmed',
                'min:6',
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                "regex:/[@$!%*#?&']/", // must contain a special character
            ];
        }
        if ($this->input('user.password')) {
            $rules['user.password'] = [
                'confirmed',
                'min:6',             // must be at least 8 characters in length
                'regex:/[a-z]/',      // must contain at least one lowercase letter
                'regex:/[A-Z]/',      // must contain at least one uppercase letter
                'regex:/[0-9]/',      // must contain at least one digit
                "regex:/[@$!%*#?&']/", // must contain a special character
            ];
        }
        $rules['user.cpassword'] = 'min:6';
        if (array_key_exists('status', $this->user) && empty($this->user['status'])) {
            $rules['user.status'] = 'required|integer';
        }
        if (array_key_exists('role_id', $this->user) && empty($this->user['role_id'])) {
            $rules['user.role_id'] = 'required|integer';
        }

        return $rules;
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
            'user.*.required' => trans('user::user.messages.required'),
            'user.email.email' => trans('user::user.messages.invalid_email'),
            'user.email.unique' => 'Email address is already in use.',
            'user.status.integer' => trans('user::user.messages.status_invalid'),
            'user.role_id.integer' => trans('user::user.messages.role_invalid'),
            'user.password.min' => trans('user::user.messages.password_length'),
            'user.password.regex' => trans('user::user.messages.password_regex'),
            'password.min' => trans('user::user.messages.password_length'),
            'password.regex' => trans('user::user.messages.password_regex'),
        ];
    }
}
