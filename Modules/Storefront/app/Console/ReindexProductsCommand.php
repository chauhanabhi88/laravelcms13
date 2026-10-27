<?php

declare(strict_types=1);

namespace Modules\Storefront\Console;

use Illuminate\Console\Command;
use Modules\Storefront\Services\Search\SearchIndexer;
use Modules\Storefront\Support\StorefrontCache;

/**
 * Backfills / repairs the storefront search index: removes rows of products that are no
 * longer live and re-indexes every live product (optionally one seller's or one category
 * subtree's).
 */
class ReindexProductsCommand extends Command
{
    protected $signature = 'storefront:reindex
        {--seller= : Only this seller id}
        {--category= : Only this category id and its descendants}';

    protected $description = 'Rebuild the storefront product search index';

    public function handle(SearchIndexer $indexer, StorefrontCache $cache): int
    {
        $seller = $this->option('seller');
        $category = $this->option('category');

        foreach (['seller' => $seller, 'category' => $category] as $name => $value) {
            if ($value !== null && ! ctype_digit((string) $value)) {
                $this->error("--{$name} must be a numeric id.");

                return self::INVALID;
            }
        }

        $total = $indexer->reindex(
            $seller !== null ? (int) $seller : null,
            $category !== null ? (int) $category : null,
            function (int $done): void {
                $this->output->write("\r  Indexed {$done} products");
            },
        );

        $cache->flush('listing');
        $cache->flush('home');

        $this->newLine();
        $this->info("Search index rebuilt: {$total} products.");

        return self::SUCCESS;
    }
}
