<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Returns\Models\ReturnRequest;

/**
 * Wizard step 1: return or exchange, the reason, a comment and the items (selected, quantity,
 * for an exchange the new variant). Eligibility is checked by the controller and again by
 * ReturnService::create().
 */
class ReturnReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ReturnRequest::TYPES)],
            'reason_code' => ['required', Rule::in((array) config('returns.reasons', []))],
            'comment' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array'],
            'items.*.selected' => ['nullable', 'in:1'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'items.*.exchange_variant_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => trans('returns::front.wizard.type'),
            'reason_code' => trans('returns::front.wizard.reason'),
            'comment' => trans('returns::front.wizard.comment'),
            'items' => trans('returns::front.wizard.items'),
        ];
    }
}
