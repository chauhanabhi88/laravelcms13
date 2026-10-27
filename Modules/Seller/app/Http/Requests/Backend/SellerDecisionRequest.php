<?php

declare(strict_types=1);

namespace Modules\Seller\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reason for an admin decision (approve: optional note; reject / request changes /
 * suspend: required, enforced by SellerVerificationService).
 */
class SellerDecisionRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ?string
    {
        $reason = trim((string) $this->validated('reason'));

        return $reason === '' ? null : $reason;
    }
}
