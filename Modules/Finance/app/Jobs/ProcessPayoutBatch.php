<?php

declare(strict_types=1);

namespace Modules\Finance\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Services\PayoutService;

/**
 * Send the payouts of an approved batch (plan M17 §5.3 step 3), one at a time.
 */
class ProcessPayoutBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public int $batchId) {}

    public function uniqueId(): string
    {
        return (string) $this->batchId;
    }

    public function handle(PayoutService $payouts): void
    {
        $batch = PayoutBatch::query()->find($this->batchId);

        if ($batch !== null) {
            $payouts->process($batch);
        }
    }
}
