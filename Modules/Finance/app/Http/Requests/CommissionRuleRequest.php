<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\FeeRule;

/**
 * Commission rule (and, with the fee fields, fee rule) form: scope with its category / seller,
 * type and value (a percentage is at most 100), effective dates.
 */
class CommissionRuleRequest extends FormRequest
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
        $isFee = $this->routeIs('admin.fee_rule.*');

        return [
            'scope' => ['required', Rule::in(CommissionRule::SCOPES)],
            'catalog_category_id' => ['nullable', 'required_if:scope,category,seller_category', 'integer', 'exists:catalog_category,id'],
            'seller_id' => ['nullable', 'required_if:scope,seller,seller_category', 'integer', 'exists:seller,id'],
            'type' => ['required', Rule::in($isFee ? FeeRule::TYPES : CommissionRule::TYPES)],
            'value' => ['required', 'numeric', 'min:0', 'max:999999.99', Rule::when(fn (): bool => $this->input('type') === 'percent', ['max:100'])],
            'applies_to' => [$isFee ? 'nullable' : 'required', Rule::in(CommissionRule::APPLIES_TO)],
            'code' => [$isFee ? 'required' : 'nullable', Rule::in((array) config('finance.fee_codes', []))],
            'charged_to' => [$isFee ? 'required' : 'nullable', Rule::in(FeeRule::CHARGED_TO)],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'status' => ['required', Rule::in([(int) config('core.enabled'), (int) config('core.disabled')])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['scope', 'catalog_category_id', 'seller_id', 'type', 'value', 'applies_to', 'code', 'charged_to', 'effective_from', 'effective_to', 'status'])
            ->mapWithKeys(fn (string $field): array => [$field => trans('finance::finance.rule.'.$field)])
            ->all();
    }
}
