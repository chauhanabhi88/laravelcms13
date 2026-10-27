<?php

declare(strict_types=1);

namespace Modules\Directory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Directory\Models\DirectoryCountryState;
use Modules\Directory\Models\DirectoryPincode;

class DirectoryPincodeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $yesNo = [config('core.yes'), config('core.no')];

        return [
            'directory_pincode.office_name' => ['required', 'string', 'max:150'],
            'directory_pincode.district' => ['required', 'string', 'max:100'],
            'directory_pincode.state_id' => ['nullable', 'integer', Rule::exists((new DirectoryCountryState)->getTable(), 'id')],
            'directory_pincode.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'directory_pincode.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'directory_pincode.is_serviceable' => ['required', Rule::in($yesNo)],
            'directory_pincode.cod_allowed' => ['required', Rule::in($yesNo)],
            'directory_pincode.zone' => ['nullable', Rule::in(DirectoryPincode::ZONES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'directory_pincode.*.required' => trans('core::core.messages.required_field'),
        ];
    }
}
