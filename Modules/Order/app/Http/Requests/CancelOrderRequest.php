<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Customer cancellation (web and API): optional item ids (none = everything that can still be
 * cancelled), a reason code and an optional note.
 */
class CancelOrderRequest extends FormRequest
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
            'items' => ['nullable', 'array', 'max:100'],
            'items.*' => ['integer'],
            'reason' => ['required', 'string', Rule::in((array) config('order.cancel_reasons', []))],
            'comment' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => trans('order::front.cancel.reason')];
    }

    /**
     * @return list<int>|null
     */
    public function itemIds(): ?array
    {
        $items = array_values(array_unique(array_map('intval', (array) $this->input('items', []))));

        return $items === [] ? null : $items;
    }

    /**
     * The reason as stored on the order: the label, plus the buyer's note.
     */
    public function reasonText(): string
    {
        $label = trans('order::front.cancel.reasons.'.$this->input('reason'));
        $comment = trim((string) $this->input('comment'));

        return mb_substr($comment === '' ? $label : $label.': '.$comment, 0, 255);
    }
}
