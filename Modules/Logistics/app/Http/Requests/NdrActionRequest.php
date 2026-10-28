<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Logistics\Models\NdrCase;

/**
 * The decision on a failed delivery: re-attempt, re-attempt at a new address/phone, or cancel
 * (return to origin).
 */
class NdrActionRequest extends FormRequest
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
        return [
            'action' => ['required', Rule::in((array) config('logistics.ndr_actions'))],
            'phone' => ['nullable', 'regex:/^[6-9][0-9]{9}$/'],
            'address1' => ['nullable', 'required_if:action,'.NdrCase::ACTION_CHANGE_ADDRESS, 'string', 'max:200'],
            'address2' => ['nullable', 'string', 'max:200'],
            'comments' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function actionPayload(): array
    {
        return array_filter([
            'phone' => (string) $this->validated('phone'),
            'address1' => (string) $this->validated('address1'),
            'address2' => (string) $this->validated('address2'),
            'comments' => (string) $this->validated('comments'),
        ], fn (string $value): bool => trim($value) !== '');
    }
}
