<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\Product;
use Modules\Promotion\Models\Coupon;
use Modules\Seller\Support\CurrentSeller;

/**
 * Supplier coupon form (plan M18 §6 "Seller (S)"): the seller's own coupon, funded by them and
 * limited to all their products or to chosen ones (only their own product ids pass). Updates of
 * another seller's coupon answer 404 before validation.
 */
class SellerCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = (int) $this->route('id');

        if ($id > 0) {
            $coupon = Coupon::query()->find($id);
            abort_if($coupon === null || $coupon->seller_id !== $this->sellerId(), 404);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sellerId = $this->sellerId();

        return [
            'code' => ['required', 'string', 'max:30', 'regex:'.config('promotion.code_pattern'), Rule::unique('coupon', 'code')->ignore((int) $this->route('id') ?: null)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in([Coupon::TYPE_PERCENT, Coupon::TYPE_FLAT])],
            'value' => ['required', 'numeric', 'gt:0', 'max:99999999.99', Rule::when(fn (): bool => $this->input('type') === Coupon::TYPE_PERCENT, ['max:100'])],
            'max_discount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'min_cart_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'scope' => ['required', Rule::in((array) config('promotion.seller_scopes'))],
            'product_ids' => ['nullable', 'array', 'required_if:scope,product'],
            'product_ids.*' => ['integer', Rule::exists('product', 'id')->where('seller_id', $sellerId)],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'is_visible' => ['required', Rule::in([(int) config('core.yes'), (int) config('core.no')])],
        ];
    }

    /**
     * Fields for CouponService::save(), with the seller's locked funding and scope.
     *
     * @return array<string, mixed>
     */
    public function couponData(): array
    {
        $data = $this->safe()->except(['product_ids']);
        $sellerId = $this->sellerId();
        $isProducts = $data['scope'] === Coupon::SCOPE_PRODUCT;

        return array_merge($data, [
            'max_discount' => $data['type'] === Coupon::TYPE_PERCENT && ($data['max_discount'] ?? '') !== '' ? $data['max_discount'] : null,
            'min_cart_value' => ($data['min_cart_value'] ?? '') !== '' ? $data['min_cart_value'] : '0',
            'usage_limit_total' => ($data['usage_limit_total'] ?? '') !== '' ? $data['usage_limit_total'] : null,
            'usage_limit_per_customer' => ($data['usage_limit_per_customer'] ?? '') !== '' ? $data['usage_limit_per_customer'] : null,
            'scope_ids' => $isProducts
                ? Product::query()->where('seller_id', $sellerId)->whereIn('id', (array) $this->validated('product_ids', []))->pluck('id')->all()
                : [$sellerId],
            'funded_by' => Coupon::FUNDED_BY_SELLER,
            'seller_id' => $sellerId,
            'customer_id' => null,
            'first_order_only' => (int) config('core.no'),
            'is_auto' => (int) config('core.no'),
            'stackable' => (int) config('core.no'),
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_visible' => $this->boolean('is_visible') ? (int) config('core.yes') : (int) config('core.no'),
            'product_ids' => array_values(array_filter((array) $this->input('product_ids', []), fn ($id): bool => $id !== null && $id !== '')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => trans('promotion::coupon.messages.code_format'),
            'product_ids.*.exists' => trans('promotion::seller.errors.not_your_product'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['code', 'name', 'description', 'type', 'value', 'max_discount', 'min_cart_value', 'scope', 'usage_limit_total', 'usage_limit_per_customer', 'starts_at', 'ends_at', 'is_visible'])
            ->mapWithKeys(fn (string $field): array => [$field => trans('promotion::coupon.fields.'.$field)])
            ->all();
    }

    protected function sellerId(): int
    {
        return (int) app(CurrentSeller::class)->seller()?->id;
    }
}
