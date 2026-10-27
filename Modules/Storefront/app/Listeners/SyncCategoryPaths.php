<?php

declare(strict_types=1);

namespace Modules\Storefront\Listeners;

use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Storefront\Services\Search\SearchIndexer;

/**
 * Keeps the index's category paths in step with tree moves. Renamed categories change the
 * searchable text only after `php artisan storefront:reindex --category=<id>`.
 */
class SyncCategoryPaths
{
    public function __construct(protected SearchIndexer $indexer) {}

    public function handle(CatalogTreeChanged $event): void
    {
        try {
            $this->indexer->syncCategoryPaths();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
