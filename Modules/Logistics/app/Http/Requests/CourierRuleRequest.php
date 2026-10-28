<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Logistics\Models\CourierRule;

/**
 * Courier rule builder (plan M13 §7): multi-selects for zones and payment, comma lists for
 * pincode prefixes, state/seller/category ids and preferred carrier ids, a weight band.
 */
class CourierRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'courier_id' => ['required', 'integer', Rule::exists('courier', 'id')],
            'strategy' => ['required', Rule::in((array) config('logistics.strategies'))],
            'preferred_carrier_ids' => ['nullable', 'array', 'max:20'],
            'preferred_carrier_ids.*' => ['string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'zones' => ['nullable', 'array'],
            'zones.*' => [Rule::in((array) config('logistics.zones'))],
            'state_ids' => ['nullable', 'array', 'max:50'],
            'state_ids.*' => ['integer', 'min:1'],
            'pincode_prefixes' => ['nullable', 'array', 'max:200'],
            'pincode_prefixes.*' => ['string', 'regex:/^[1-9][0-9]{0,5}$/'],
            'weight_min_g' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'weight_max_g' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'payment' => ['nullable', 'array'],
            'payment.*' => [Rule::in(['cod', 'prepaid'])],
            'seller_ids' => ['nullable', 'array', 'max:200'],
            'seller_ids.*' => ['integer', 'min:1'],
            'category_ids' => ['nullable', 'array', 'max:200'],
            'category_ids.*' => ['integer', 'min:1'],
            'status' => ['required', Rule::in([config('core.enabled'), config('core.disabled')])],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('strategy') === CourierRule::STRATEGY_PREFERRED_LIST && (array) $this->input('preferred_carrier_ids', []) === []) {
                    $validator->errors()->add('preferred_carrier_ids', trans('logistics::courier_rule.messages.preferred_required'));
                }

                $min = $this->input('weight_min_g');
                $max = $this->input('weight_max_g');

                if (is_numeric($min) && is_numeric($max) && (int) $max < (int) $min) {
                    $validator->errors()->add('weight_max_g', trans('logistics::courier_rule.messages.weight_band'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $ints = fn (string $key): array => array_values(array_unique(array_map('intval', (array) $this->validated($key, []))));
        $strings = fn (string $key): array => array_values(array_unique(array_map('strval', (array) $this->validated($key, []))));

        $conditions = array_filter([
            'zones' => $strings('zones'),
            'state_ids' => $ints('state_ids'),
            'pincode_prefixes' => $strings('pincode_prefixes'),
            'weight_min_g' => $this->validated('weight_min_g') !== null ? (int) $this->validated('weight_min_g') : null,
            'weight_max_g' => $this->validated('weight_max_g') !== null ? (int) $this->validated('weight_max_g') : null,
            'payment' => $strings('payment'),
            'seller_ids' => $ints('seller_ids'),
            'category_ids' => $ints('category_ids'),
        ], fn ($value): bool => $value !== null && $value !== []);

        return [
            'name' => $this->validated('name'),
            'priority' => (int) $this->validated('priority'),
            'courier_id' => (int) $this->validated('courier_id'),
            'strategy' => $this->validated('strategy'),
            'preferred_carrier_ids' => ($ids = $strings('preferred_carrier_ids')) === [] ? null : $ids,
            'conditions' => $conditions === [] ? null : $conditions,
            'status' => $this->validated('status'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->filled('status') ? config('core.enabled') : config('core.disabled'),
            'preferred_carrier_ids' => self::commaList($this->input('preferred_carrier_ids')),
            'state_ids' => self::commaList($this->input('state_ids')),
            'pincode_prefixes' => self::commaList($this->input('pincode_prefixes')),
            'seller_ids' => self::commaList($this->input('seller_ids')),
            'category_ids' => self::commaList($this->input('category_ids')),
            'zones' => array_values(array_filter((array) $this->input('zones', []))),
            'payment' => array_values(array_filter((array) $this->input('payment', []))),
        ]);
    }

    /**
     * "560, 110 ,400" (or an array) → ['560', '110', '400'].
     *
     * @return list<string>
     */
    public static function commaList(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn ($part): string => trim((string) $part), $parts), fn (string $part): bool => $part !== ''));
    }
}
