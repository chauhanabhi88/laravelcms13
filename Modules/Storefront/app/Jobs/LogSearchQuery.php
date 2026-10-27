<?php

declare(strict_types=1);

namespace Modules\Storefront\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Storefront\Services\Search\SearchAnalytics;

/**
 * Writes one search_query_log row. Dispatched with dispatchAfterResponse(), so it runs in the
 * same process once the results page has been sent.
 */
class LogSearchQuery
{
    use Dispatchable;

    public function __construct(
        public string $term,
        public int $results,
        public ?int $customerId,
        public ?string $sessionId,
    ) {}

    public function handle(SearchAnalytics $analytics): void
    {
        try {
            $analytics->log($this->term, $this->results, $this->customerId, $this->sessionId);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
