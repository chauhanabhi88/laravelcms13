<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Logistics\Support\LogisticsOptions;

/**
 * A scan recorded by Logistics Ops for a manual-courier shipment.
 */
class ManualStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(array_keys(LogisticsOptions::manualStatuses()))],
            'location' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
