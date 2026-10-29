<?php

declare(strict_types=1);

namespace Modules\Finance\DTO;

use Modules\Core\Support\Money;

/**
 * One transfer to send: a seller payout, a reseller withdrawal or a COD refund.
 *
 * beneficiary: ['type' => 'bank', 'name', 'account_number', 'ifsc', 'fund_account_id' (optional)]
 * or ['type' => 'vpa', 'name', 'vpa'].
 */
final readonly class PayoutRequest
{
    public const PURPOSE_PAYOUT = 'payout';

    public const PURPOSE_REFUND = 'refund';

    public const PURPOSE_CASHBACK = 'cashback';

    /**
     * @param  array<string, string|null>  $beneficiary
     * @param  array<string, string>  $notes
     */
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $idempotencyKey,
        public array $beneficiary,
        public string $purpose = self::PURPOSE_PAYOUT,
        public string $narration = '',
        public string $contactReference = '',
        public array $notes = [],
    ) {}

    public function isVpa(): bool
    {
        return ($this->beneficiary['type'] ?? null) === 'vpa';
    }
}
