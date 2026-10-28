<?php

declare(strict_types=1);

namespace Modules\Returns\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Returns\Support\Validation\RefundDetailsRules;

/**
 * Wizard step 3: the COD refund details (UPI id or bank account) and the pickup address. Whether
 * the details are required (a COD return) is decided by the controller from the draft.
 */
class ReturnRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return RefundDetailsRules::rules(false) + [
            'pickup_address_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'refund_mode' => trans('returns::front.refund.mode'),
            'vpa' => trans('returns::front.refund.vpa'),
            'account_holder' => trans('returns::front.refund.account_holder'),
            'account_number' => trans('returns::front.refund.account_number'),
            'account_number_confirmation' => trans('returns::front.refund.account_number_confirmation'),
            'ifsc' => trans('returns::front.refund.ifsc'),
        ];
    }
}
