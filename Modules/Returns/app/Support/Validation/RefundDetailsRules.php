<?php

declare(strict_types=1);

namespace Modules\Returns\Support\Validation;

use Illuminate\Validation\Rule;
use Modules\Seller\Support\Validation\Ifsc;

/**
 * Validation of COD refund details (FR-RET-004), shared by the web wizard and the API: a UPI
 * id, or a bank account (holder, 9–18 digits typed twice, IFSC).
 */
final class RefundDetailsRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $required): array
    {
        return [
            'refund_mode' => [$required ? 'required' : 'nullable', Rule::in(['bank', 'upi'])],
            'vpa' => ['nullable', 'required_if:refund_mode,upi', 'string', 'max:256', new Vpa],
            'account_holder' => ['nullable', 'required_if:refund_mode,bank', 'string', 'min:2', 'max:100'],
            'account_number' => ['nullable', 'required_if:refund_mode,bank', 'string', new BankAccount],
            'account_number_confirmation' => ['nullable', 'required_if:refund_mode,bank', 'same:account_number'],
            'ifsc' => ['nullable', 'required_if:refund_mode,bank', 'string', new Ifsc],
        ];
    }

    /**
     * The beneficiary in ReturnSubmission's shape, from validated input.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>|null
     */
    public static function beneficiary(array $input): ?array
    {
        return match ($input['refund_mode'] ?? null) {
            'upi' => ['vpa' => strtolower(trim((string) $input['vpa']))],
            'bank' => [
                'account_holder' => trim((string) $input['account_holder']),
                'account_number' => (string) preg_replace('/\s+/', '', (string) $input['account_number']),
                'ifsc' => strtoupper(trim((string) $input['ifsc'])),
            ],
            default => null,
        };
    }
}
