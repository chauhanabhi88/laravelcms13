<?php

declare(strict_types=1);

namespace Modules\Finance\Console;

use Illuminate\Console\Command;
use Modules\Finance\Jobs\ReleaseEligibleEntries;

/**
 * finance:release-eligible (daily from the Cron module). Runs inline.
 */
class ReleaseEligibleEntriesCommand extends Command
{
    protected $signature = 'finance:release-eligible';

    protected $description = 'Make seller ledger entries past their return window and payout delay payable (or held), and release reseller margins.';

    public function handle(): int
    {
        $result = app()->call([new ReleaseEligibleEntries, 'handle']);
        $this->info(sprintf('Released %d, held %d, re-synced %d ledger entries; %d wallet entries available.', $result['released'], $result['on_hold'], $result['resynced'], $result['wallet']));

        return self::SUCCESS;
    }
}
