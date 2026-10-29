<?php

declare(strict_types=1);

namespace Modules\Finance\Listeners;

use Modules\Finance\Services\LedgerPosterImpl;
use Modules\Finance\Services\ResellerWalletService;
use Modules\Returns\Events\ReturnRefunded;
use Modules\Returns\Models\ReturnRequest;
use Throwable;

/**
 * A return was refunded (M15): the sale and its deductions are reversed for the returned
 * units, and a reseller's margin on them too (plan M17 §5.2, §5.8).
 */
class PostReturnReversal
{
    public function __construct(
        private readonly LedgerPosterImpl $ledger,
        private readonly ResellerWalletService $wallet,
    ) {}

    public function handle(ReturnRefunded $event): void
    {
        try {
            $return = ReturnRequest::query()->findOrFail($event->returnRequest->id);
            $this->ledger->postReturnRefunded($return);
            $this->wallet->reverseForReturn($return);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
