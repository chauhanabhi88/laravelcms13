<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Approve / reject one queued product: a free-text reason and reason template codes.
 */
class ProductQcDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
            'reason_codes' => ['nullable', 'array'],
            'reason_codes.*' => ['string', Rule::in((array) config('product.qc_reason_templates'))],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }

    /**
     * @return list<string>
     */
    public function reasonCodes(): array
    {
        return array_values(array_map('strval', (array) $this->validated('reason_codes', [])));
    }
}
