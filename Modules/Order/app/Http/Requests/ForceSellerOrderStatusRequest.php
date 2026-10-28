<?php

declare(strict_types=1);

namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Order\Models\SellerOrder;

/**
 * Ops override of a seller order's status (admin.seller_order.force_status): the target status
 * (still bound by the state machine) and a mandatory reason for the audit trail.
 */
class ForceSellerOrderStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(array_diff(SellerOrder::STATUSES, [SellerOrder::STATUS_CANCELLED]))],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => trans('order::seller_order.labels.status'),
            'reason' => trans('order::seller_order.labels.reason'),
        ];
    }
}
