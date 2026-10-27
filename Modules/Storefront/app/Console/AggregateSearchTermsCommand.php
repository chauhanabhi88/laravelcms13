<?php

declare(strict_types=1);

namespace Modules\Storefront\Console;

use Illuminate\Console\Command;
use Modules\Storefront\Services\Search\SearchAnalytics;

/**
 * Nightly roll-up of the search log into search_term_stat (trending terms, zero-result terms,
 * click-through) and pruning of old log rows. Scheduled by the Cron module (migration
 * 2026_11_06_000008).
 */
class AggregateSearchTermsCommand extends Command
{
    protected $signature = 'storefront:aggregate-search-terms';

    protected $description = 'Aggregate storefront search terms and prune the search log';

    public function handle(SearchAnalytics $analytics): int
    {
        $result = $analytics->aggregate();

        $this->info("Aggregated {$result['terms']} search terms; pruned {$result['pruned']} old log rows.");

        return self::SUCCESS;
    }
}
