<?php

declare(strict_types=1);

namespace Modules\Customer\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Customer\Models\CustomerDataRequest;
use Modules\Customer\Services\AccountDeletionService;

/**
 * Clears a customer's PII once their deletion grace period is over (FR-CUS-008).
 * Queued by `customer:process-deletions`; a held-back request stays pending for the next run.
 */
class AnonymiseCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $dataRequestId) {}

    public function handle(AccountDeletionService $deletions): void
    {
        $request = CustomerDataRequest::query()->find($this->dataRequestId);

        if ($request !== null) {
            $deletions->anonymise($request);
        }
    }
}
