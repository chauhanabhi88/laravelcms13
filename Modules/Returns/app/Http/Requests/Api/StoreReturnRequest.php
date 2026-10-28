<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Modules\Returns\Models\ReturnRequest;
use Modules\Returns\Support\ReturnSettings;
use Modules\Returns\Support\Validation\RefundDetailsRules;

/**
 * POST /api/v1/returns (multipart): the package, type, reason, items, photos[], COD refund
 * details and an optional saved pickup address. Errors use the API envelope.
 */
class StoreReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return RefundDetailsRules::rules(false) + [
            'seller_order_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(ReturnRequest::TYPES)],
            'reason_code' => ['required', Rule::in((array) config('returns.reasons', []))],
            'comment' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.exchange_variant_id' => ['nullable', 'integer', 'required_if:type,exchange'],
            'photos' => ['nullable', 'array', 'max:'.ReturnSettings::maxImages()],
            'photos.*' => ['file', 'image', 'mimes:'.implode(',', (array) config('returns.uploads.mimes', ['jpg', 'jpeg', 'png', 'webp'])), 'max:'.(int) config('returns.uploads.max_size_kb', 5120)],
            'pickup_address_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<int, array{quantity: int, exchange_variant_id: int|null}>
     */
    public function items(): array
    {
        $items = [];

        foreach ((array) $this->validated('items') as $line) {
            $items[(int) $line['item_id']] = [
                'quantity' => (int) $line['quantity'],
                'exchange_variant_id' => isset($line['exchange_variant_id']) ? (int) $line['exchange_variant_id'] : null,
            ];
        }

        return $items;
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values(array_filter((array) $this->file('photos', []), fn ($file): bool => $file instanceof UploadedFile));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'code' => '422',
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
