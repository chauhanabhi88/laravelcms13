<?php

declare(strict_types=1);

namespace Modules\Seller\Support;

/**
 * What a BankVerifier learned. BankAccountService turns it into a verification status
 * (name match against the seller's names decides verified vs manual review).
 */
final readonly class BankVerificationResult
{
    /** The provider is still checking; a webhook delivers the answer. */
    public const PENDING = 'pending';

    /** The provider reached the account and returned the holder's name. */
    public const COMPLETED = 'completed';

    /** The account could not be validated (wrong number, closed account, provider error). */
    public const FAILED = 'failed';

    /** No automatic check: an admin reviews the cancelled cheque. */
    public const MANUAL = 'manual';

    public function __construct(
        public string $outcome,
        public ?string $registeredName = null,
        public bool $accountActive = false,
        public ?string $providerRef = null,
        public ?string $contactId = null,
        public ?string $fundAccountId = null,
        public ?string $message = null,
    ) {}

    public static function pending(string $providerRef, ?string $contactId = null, ?string $fundAccountId = null): self
    {
        return new self(self::PENDING, providerRef: $providerRef, contactId: $contactId, fundAccountId: $fundAccountId);
    }

    public static function completed(?string $registeredName, bool $accountActive = true, ?string $providerRef = null, ?string $contactId = null, ?string $fundAccountId = null): self
    {
        return new self(self::COMPLETED, $registeredName, $accountActive, $providerRef, $contactId, $fundAccountId);
    }

    public static function failed(?string $message = null, ?string $providerRef = null, ?string $contactId = null, ?string $fundAccountId = null): self
    {
        return new self(self::FAILED, providerRef: $providerRef, contactId: $contactId, fundAccountId: $fundAccountId, message: $message);
    }

    public static function manual(): self
    {
        return new self(self::MANUAL);
    }
}
