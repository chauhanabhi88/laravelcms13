<?php

declare(strict_types=1);

namespace Modules\Finance\DTO;

/**
 * A gateway's answer to a payout: processing (sent, await the webhook or a UTR upload),
 * paid (with the bank UTR) or failed.
 */
final readonly class PayoutResult
{
    public const PROCESSING = 'processing';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,
        public ?string $gatewayPayoutId = null,
        public ?string $utr = null,
        public ?string $error = null,
        public array $raw = [],
    ) {}

    public static function processing(?string $gatewayPayoutId = null): self
    {
        return new self(self::PROCESSING, $gatewayPayoutId);
    }

    public static function paid(?string $gatewayPayoutId, ?string $utr): self
    {
        return new self(self::PAID, $gatewayPayoutId, $utr);
    }

    public static function failed(string $error, ?string $gatewayPayoutId = null): self
    {
        return new self(self::FAILED, $gatewayPayoutId, null, $error);
    }
}
