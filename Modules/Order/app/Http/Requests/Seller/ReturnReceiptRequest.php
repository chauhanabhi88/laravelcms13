<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Support\CurrentSeller;

/**
 * The seller's receipt of an RTO package (POST seller.order.return_receipt): its condition,
 * and for a damaged or wrong item a note and 1–5 photos for the claim. Another seller's order
 * answers 404 before validation runs.
 */
class ReturnReceiptRequest extends FormRequest
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
        $max = max(1, (int) config('order.fulfilment.claim_max_photos', 5));

        return [
            'condition' => ['required', Rule::in((array) config('order.fulfilment.receipt_conditions'))],
            'note' => ['nullable', 'string', 'max:500', 'required_unless:condition,good'],
            'photos' => ['nullable', 'array', 'max:'.$max, 'required_unless:condition,good'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'condition' => trans('order::fulfilment.receipt.condition'),
            'note' => trans('order::fulfilment.receipt.note'),
            'photos' => trans('order::fulfilment.receipt.photos'),
        ];
    }

    public function sellerOrder(): SellerOrder
    {
        return $this->resolvedSellerOrder ??= SellerOrder::query()->findOrFail((int) $this->route('id'));
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), fn ($file): bool => $file instanceof UploadedFile));
    }
}
