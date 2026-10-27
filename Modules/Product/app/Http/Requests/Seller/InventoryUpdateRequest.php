<?php

declare(strict_types=1);

namespace Modules\Product\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Support\CurrentSeller;

/**
 * Inline edit on the inventory screen (PATCH seller.inventory.update): stock, threshold,
 * status and optionally price / MRP of one variant. Another seller's variant answers 404.
 */
class InventoryUpdateRequest extends FormRequest
{
    protected ?ProductVariant $resolvedVariant = null;

    public function authorize(): bool
    {
        Gate::forUser(app(CurrentSeller::class)->user())->authorize('manage', $this->variant());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = ['nullable', 'regex:/^\d{1,10}(\.\d{1,2})?$/'];

        return [
            'stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'status' => ['nullable', Rule::in([(int) config('core.enabled'), (int) config('core.disabled')])],
            'mrp' => $money,
            'price' => $money,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'stock' => trans('product::seller.wizard.columns.stock'),
            'low_stock_threshold' => trans('product::seller.wizard.columns.low_stock_threshold'),
            'mrp' => trans('product::seller.wizard.columns.mrp'),
            'price' => trans('product::seller.wizard.columns.price'),
        ];
    }

    public function variant(): ProductVariant
    {
        return $this->resolvedVariant ??= ProductVariant::query()->whereHas('product')->findOrFail((int) $this->route('variant'));
    }

    /**
     * Only the fields sent, typed for VariantQuickEditService.
     *
     * @return array{mrp?: string, price?: string, stock?: int, low_stock_threshold?: int, status?: int}
     */
    public function changes(): array
    {
        $changes = [];

        foreach (['stock', 'low_stock_threshold', 'status'] as $field) {
            if ($this->filled($field)) {
                $changes[$field] = (int) $this->input($field);
            }
        }

        foreach (['mrp', 'price'] as $field) {
            if ($this->filled($field)) {
                $changes[$field] = (string) $this->input($field);
            }
        }

        return $changes;
    }
}
