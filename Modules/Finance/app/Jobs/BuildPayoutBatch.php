<?php

declare(strict_types=1);

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Services\LedgerReleaseService;
use Modules\Finance\Services\PayoutService;

/**
 * Weekly on the payout day (plan M17 §5.3 step 1): release what became payable today, then
 * build the draft batch for Finance to approve.
 */
class BuildPayoutBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(LedgerReleaseService $release, PayoutService $payouts): ?PayoutBatch
    {
        $release->run();

        return $payouts->build();
    }
}
