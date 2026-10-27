<?php

declare(strict_types=1);

namespace Modules\Customer\Console;

use Illuminate\Console\Command;
use Modules\Customer\Jobs\AnonymiseCustomer;
use Modules\Customer\Models\CustomerDataRequest;

/**
 * Queues AnonymiseCustomer for every deletion request whose grace period is over.
 * Scheduled daily through the Cron module ("cron" table row added by the M08 migration).
 */
class ProcessAccountDeletionsCommand extends Command
{
    protected $signature = 'customer:process-deletions';

    protected $description = 'Anonymise customer accounts whose deletion grace period is over';

    public function handle(): int
    {
        $count = 0;

        CustomerDataRequest::query()->dueDeletions()->orderBy('id')->select('id')->chunkById(200, function ($requests) use (&$count): void {
            foreach ($requests as $request) {
                AnonymiseCustomer::dispatch($request->id);
                $count++;
            }
        });

        $this->info("Queued {$count} account deletion(s).");

        return self::SUCCESS;
    }
}
