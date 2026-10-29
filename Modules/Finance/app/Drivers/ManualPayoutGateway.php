<?php

declare(strict_types=1);

namespace Modules\Finance\Drivers;

use Modules\Finance\Contracts\PayoutGateway;
use Modules\Finance\DTO\PayoutRequest;
use Modules\Finance\DTO\PayoutResult;

/**
 * Manual bank transfers (the MVP rail): Finance downloads the bank bulk-transfer file,
 * transfers, then uploads the UTRs (or enters one per payout), which marks payouts paid.
 */
class ManualPayoutGateway implements PayoutGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function isAutomatic(): bool
    {
        return false;
    }

    public function pay(PayoutRequest $request): PayoutResult
    {
        return PayoutResult::processing();
    }
}
