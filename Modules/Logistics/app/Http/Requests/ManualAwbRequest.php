<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Logistics Ops enter the AWB of a manual-courier shipment.
 */
class ManualAwbRequest extends FormRequest
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
            'awb' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('shipment', 'awb')],
            'carrier_name' => ['nullable', 'string', 'max:100'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['awb' => trim((string) $this->input('awb'))]);
    }
}
