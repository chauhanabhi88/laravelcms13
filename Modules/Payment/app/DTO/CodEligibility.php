<?php

declare(strict_types=1);

namespace Modules\Payment\DTO;

/**
 * Whether Cash on Delivery may be offered (FR-PAY-004), with a buyer-facing reason when not.
 */
final readonly class CodEligibility
{
    public const REASON_DISABLED = 'disabled';

    public const REASON_PINCODE = 'pincode';

    public const REASON_ABOVE_MAX = 'above_max';

    public const REASON_BELOW_MIN = 'below_min';

    public const REASON_ITEMS = 'items';

    public const REASON_CUSTOMER_BLOCKED = 'customer_blocked';

    public const REASON_DAILY_LIMIT = 'daily_limit';

    public const REASON_RISK = 'risk';

    public function __construct(
        public bool $allowed,
        public ?string $reasonCode = null,
        public ?string $reason = null,
        public bool $otpRequired = false,
    ) {}

    public static function allow(bool $otpRequired): self
    {
        return new self(true, null, null, $otpRequired);
    }

    public static function block(string $reasonCode, string $reason): self
    {
        return new self(false, $reasonCode, $reason);
    }

    /**
     * @return array{allowed: bool, reason_code: string|null, reason: string|null, otp_required: bool}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason_code' => $this->reasonCode,
            'reason' => $this->reason,
            'otp_required' => $this->otpRequired,
        ];
    }
}
