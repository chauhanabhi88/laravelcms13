<?php

declare(strict_types=1);

namespace Modules\Finance\Contracts;

use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;
use Modules\Finance\Exceptions\PayoutGatewayUnavailable;

/**
 * A payout rail (plan M17 §5.3): RazorpayX, manual bank transfer (UTRs uploaded by Finance)
 * or the fake used offline. pay() must be safe to repeat with the same idempotency key.
 */
interface PayoutGateway
{
    public function name(): string;

    /**
     * Whether a transfer started here completes by itself (webhook) rather than by an
     * uploaded UTR.
     */
    public function isAutomatic(): bool;

    /**
     * @throws PayoutGatewayUnavailable when the outcome is unknown (network, 5xx): retry later
     */
    public function pay(PayoutRequest $request): PayoutResult;
}
