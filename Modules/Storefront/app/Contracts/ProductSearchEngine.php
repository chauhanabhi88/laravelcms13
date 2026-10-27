<?php

declare(strict_types=1);

namespace Modules\Storefront\Contracts;

use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Services\Search\ListingResult;

/**
 * Listing and search engine. The MySQL implementation reads product_search_index; a Scout /
 * Meilisearch engine can replace it by rebinding this contract.
 */
interface ProductSearchEngine
{
    /**
     * One page of products for the query. $withTotal also counts the whole result set.
     */
    public function run(ListingQuery $query, bool $withTotal = true): ListingResult;

    /**
     * Number of products matching the query (cached briefly).
     */
    public function count(ListingQuery $query): int;
}
