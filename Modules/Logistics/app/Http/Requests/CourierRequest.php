<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Logistics\Models\Courier;

/**
 * Courier master (FR-LOG-002). Credentials are only kept for the shiprocket driver; a blank
 * password on update keeps the stored one.
 */
class CourierRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'code' => ['required', 'string', 'max:40', 'alpha_dash:ascii', Rule::unique('courier', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::in((array) config('logistics.drivers'))],
            'credentials_email' => ['nullable', 'email', 'max:150'],
            'credentials_password' => ['nullable', 'string', 'max:150'],
            'supports_cod' => ['required', Rule::in([config('core.yes'), config('core.no')])],
            'supports_reverse' => ['required', Rule::in([config('core.yes'), config('core.no')])],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'status' => ['required', Rule::in([config('core.enabled'), config('core.disabled')])],
        ];
    }

    /**
     * The row to store; $existing keeps its password when none was typed.
     *
     * @return array<string, mixed>
     */
    public function payload(?Courier $existing = null): array
    {
        $data = $this->safe()->only(['code', 'name', 'driver', 'supports_cod', 'supports_reverse', 'priority', 'status']);
        $data['code'] = strtolower((string) $data['code']);

        if ($data['driver'] !== Courier::DRIVER_SHIPROCKET) {
            $data['credentials'] = null;

            return $data;
        }

        $email = trim((string) $this->validated('credentials_email'));
        $password = (string) $this->validated('credentials_password');
        $password = $password !== '' ? $password : (string) $existing?->credential('password');

        $data['credentials'] = $email === '' && $password === '' ? null : array_filter(['email' => $email, 'password' => $password], fn (string $value): bool => $value !== '');

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'supports_cod' => $this->filled('supports_cod') ? config('core.yes') : config('core.no'),
            'supports_reverse' => $this->filled('supports_reverse') ? config('core.yes') : config('core.no'),
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
            'priority' => $this->input('priority', 0) === null ? 0 : $this->input('priority', 0),
        ]);
    }
}
