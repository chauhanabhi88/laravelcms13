<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Promotion\Models\Coupon;

/**
 * Admin coupon / automatic offer form (plan M18 §3.1). A percentage is at most 100; a scope
 * other than "all" needs its ids; a seller-funded coupon names the seller (whose products it is
 * then limited to). Check boxes arrive as 1 / 2. The bulk form (bulk = true) has no code but a
 * prefix and a count.
 */
class CouponRequest extends FormRequest
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
        $isBulk = $this->routeIs('admin.coupon.bulk_generate');
        $flags = Rule::in([(int) config('core.yes'), (int) config('core.no')]);

        return [
            'code' => $isBulk ? ['nullable'] : [
                'required', 'string', 'max:30', 'regex:'.config('promotion.code_pattern'),
                Rule::unique('coupon', 'code')->ignore((int) $this->route('id') ?: null),
            ],
            'prefix' => $isBulk ? ['required', 'string', 'regex:/^[A-Z0-9]{2,12}$/'] : ['nullable'],
            'count' => $isBulk ? ['required', 'integer', 'min:1', 'max:'.(int) config('promotion.bulk.max_count', 1000)] : ['nullable'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in((array) config('promotion.types'))],
            'value' => ['required', 'numeric', 'min:0', 'max:99999999.99', Rule::when(fn (): bool => $this->input('type') === Coupon::TYPE_PERCENT, ['gt:0', 'max:100']), Rule::when(fn (): bool => $this->input('type') === Coupon::TYPE_FLAT, ['gt:0'])],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'min_cart_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'scope' => ['required', Rule::in((array) config('promotion.scopes'))],
            'scope_ids' => ['nullable', 'array', 'required_unless:scope,all'],
            'scope_ids.*' => ['integer', 'min:1'],
            'funded_by' => ['required', Rule::in((array) config('promotion.funding'))],
            'seller_id' => ['nullable', 'required_if:funded_by,seller', 'integer', 'exists:seller,id'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'first_order_only' => ['required', $flags],
            'new_customer_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in((array) config('promotion.channels'))],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => [Rule::in((array) config('promotion.payment_methods'))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_auto' => ['required', $flags],
            'is_visible' => ['required', $flags],
            'stackable' => ['required', $flags],
            'status' => ['required', Rule::in([(int) config('core.enabled'), (int) config('core.disabled')])],
        ];
    }

    /**
     * Fields for CouponService::save() (no code / prefix / count).
     *
     * @return array<string, mixed>
     */
    public function couponData(): array
    {
        $data = $this->safe()->except(['prefix', 'count']);

        foreach (['max_discount', 'usage_limit_total', 'usage_limit_per_customer', 'new_customer_days', 'starts_at', 'ends_at', 'seller_id'] as $nullable) {
            $data[$nullable] = isset($data[$nullable]) && $data[$nullable] !== '' ? $data[$nullable] : null;
        }

        $data['min_cart_value'] = $data['min_cart_value'] ?? '0';
        $data['scope_ids'] = $data['scope_ids'] ?? [];
        $data['channels'] = $data['channels'] ?? [];
        $data['payment_methods'] = $data['payment_methods'] ?? [];

        if ($data['funded_by'] === Coupon::FUNDED_BY_PLATFORM) {
            $data['seller_id'] = null;
        }

        if ($this->routeIs('admin.coupon.bulk_generate')) {
            unset($data['code']);
            $data['is_auto'] = (int) config('core.no');
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $flag = fn (string $key): int => $this->boolean($key) ? (int) config('core.yes') : (int) config('core.no');

        // The form has one target input per scope; the chosen scope's one becomes scope_ids.
        $targets = match ((string) $this->input('scope')) {
            Coupon::SCOPE_CATEGORY => $this->input('scope_category_ids'),
            Coupon::SCOPE_SELLER => $this->input('scope_seller_ids'),
            Coupon::SCOPE_BRAND => $this->input('scope_brand_ids'),
            Coupon::SCOPE_PRODUCT => is_string($this->input('scope_product_ids')) ? preg_split('/[\s,]+/', $this->input('scope_product_ids')) : $this->input('scope_product_ids'),
            default => null,
        };

        if ($targets !== null) {
            $this->merge(['scope_ids' => (array) $targets]);
        }

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'prefix' => strtoupper(trim((string) $this->input('prefix'))),
            'first_order_only' => $flag('first_order_only'),
            'is_auto' => $flag('is_auto'),
            'is_visible' => $flag('is_visible'),
            'stackable' => $flag('stackable'),
            'status' => $this->boolean('status') ? (int) config('core.enabled') : (int) config('core.disabled'),
            'scope_ids' => array_values(array_filter((array) $this->input('scope_ids', []), fn ($id): bool => $id !== null && $id !== '')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(array_keys($this->rules()))
            ->reject(fn (string $field): bool => str_contains($field, '*'))
            ->mapWithKeys(fn (string $field): array => [$field => trans('promotion::coupon.fields.'.$field)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => trans('promotion::coupon.messages.code_format')];
    }
}
