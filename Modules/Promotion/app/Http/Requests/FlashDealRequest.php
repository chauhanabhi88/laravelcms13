<?php

declare(strict_types=1);

namespace Modules\Promotion\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin flash deal form: name, a required window (end after start), an optional banner image URL,
 * the existing variant rows (items[id][deal_price|stock_cap|remove]) and new rows
 * (new_items[n][sku|deal_price|stock_cap]). Prices against each variant are checked by
 * FlashDealService.
 */
class FlashDealRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'banner' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::in([(int) config('core.enabled'), (int) config('core.disabled')])],
            'items' => ['nullable', 'array'],
            'items.*.deal_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'items.*.stock_cap' => ['nullable', 'integer', 'min:1'],
            'items.*.remove' => ['nullable'],
            'new_items' => ['nullable', 'array', 'max:'.(int) config('promotion.flash_deal_max_items', 200)],
            'new_items.*.sku' => ['nullable', 'string', 'max:32'],
            'new_items.*.deal_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'new_items.*.stock_cap' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{name: string, starts_at: string, ends_at: string, banner: string|null, status: int}
     */
    public function dealData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'starts_at' => (string) $this->validated('starts_at'),
            'ends_at' => (string) $this->validated('ends_at'),
            'banner' => $this->validated('banner') ?: null,
            'status' => (int) $this->validated('status'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['status' => $this->boolean('status') ? (int) config('core.enabled') : (int) config('core.disabled')]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['name', 'starts_at', 'ends_at', 'banner', 'status'])
            ->mapWithKeys(fn (string $field): array => [$field => trans('promotion::flash_deal.fields.'.$field)])
            ->all();
    }
}
