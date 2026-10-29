<?php

declare(strict_types=1);

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Finance\Services\LedgerReleaseService;
use Modules\Finance\Services\ResellerWalletService;

/**
 * Daily (plan M17 §5.2): seller ledger entries past their eligible date become payable (or
 * held), holds are re-applied, and reseller margins past the return window become available.
 */
class ReleaseEligibleEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @return array{released: int, on_hold: int, resynced: int, wallet: int}
     */
    public function handle(LedgerReleaseService $ledger, ResellerWalletService $wallet): array
    {
        return $ledger->run() + ['wallet' => $wallet->release()];
    }
}
