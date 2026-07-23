<?php

namespace Modules\Role\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Role\Models\Role;
use Modules\Role\Repositories\RoleRepository;

class UpsertRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $role = new Role;

        return [
            'role.name' => 'required',
            'role.slug' => 'required|unique:'.$role->getTable().',slug,'.$this->id,
            'permissions' => 'sometimes|array',
            'permissions.*' => ['string', Rule::in($this->validPermissions())],
        ];
    }

    /**
     * Flatten every module's config/permissions.php entries into the set of
     * ability strings a role may legally be granted, so the request can't be
     * used to store an arbitrary permission string.
     *
     * @return array<int, string>
     */
    private function validPermissions()
    {
        $valid = [];
        foreach (app(RoleRepository::class)->getModulePermissions() as $modulePermissionGroups) {
            foreach ($modulePermissionGroups as $group) {
                $valid = array_merge($valid, array_keys($group));
            }
        }

        return $valid;
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
            'role.*.required' => trans('role::role.messages.required'),
            'role.*.unique' => trans('role::role.messages.slug_validation'),
            'permissions.*.in' => trans('role::role.messages.invalid_permission'),
        ];
    }
}
