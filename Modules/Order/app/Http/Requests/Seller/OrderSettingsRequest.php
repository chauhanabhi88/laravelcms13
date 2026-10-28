<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Order\Models\LabelBatch;

/**
 * Supplier panel > Orders > Settings: auto-accept and the label paper (blank = platform default).
 */
class OrderSettingsRequest extends FormRequest
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
            'auto_accept_orders' => ['required', Rule::in([(int) config('core.yes'), (int) config('core.no')])],
            'label_format' => ['nullable', Rule::in(LabelBatch::FORMATS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'auto_accept_orders' => trans('order::fulfilment.settings.auto_accept'),
            'label_format' => trans('order::fulfilment.settings.label_format'),
        ];
    }
}
