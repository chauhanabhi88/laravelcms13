<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk desk actions (accept, pack, labels, manifest): the selected seller order ids, at most
 * order.fulfilment.batch_limit. Ownership is checked by the controller (other sellers' ids
 * are dropped); pickup_date is used by the manifest action only.
 */
class BulkSellerOrderRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('order.fulfilment.batch_limit', 100))],
            'ids.*' => ['integer', 'min:1'],
            'pickup_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => trans('order::fulfilment.errors.nothing_selected'),
            'ids.min' => trans('order::fulfilment.errors.nothing_selected'),
            'ids.max' => trans('order::fulfilment.errors.batch_limit', ['limit' => (int) config('order.fulfilment.batch_limit', 100)]),
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ids')));
    }
}
