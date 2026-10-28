<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Support\CurrentSeller;

/**
 * Seller cancellation of one order (POST seller.order.cancel) with a reason code and an
 * optional note. Another seller's order answers 404 before validation runs.
 */
class SellerCancelRequest extends FormRequest
{
    protected ?SellerOrder $resolvedSellerOrder = null;

    public function authorize(): bool
    {
        Gate::forUser(app(CurrentSeller::class)->user())->authorize('manage', $this->sellerOrder());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in((array) config('order.fulfilment.seller_cancel_reasons'))],
            'note' => ['nullable', 'string', 'max:200', 'required_if:reason,other'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reason' => trans('order::fulfilment.cancel.reason'),
            'note' => trans('order::fulfilment.cancel.note'),
        ];
    }

    public function sellerOrder(): SellerOrder
    {
        return $this->resolvedSellerOrder ??= SellerOrder::query()->findOrFail((int) $this->route('id'));
    }
}
