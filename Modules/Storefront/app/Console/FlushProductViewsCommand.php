<?php

declare(strict_types=1);

namespace Modules\Storefront\Console;

use Illuminate\Console\Command;
use Modules\Storefront\Services\ProductViewCounter;

/**
 * Adds the product page views collected in the cache to product.view_count (scheduled every
 * 10 minutes through the Cron module).
 */
class FlushProductViewsCommand extends Command
{
    protected $signature = 'storefront:flush-views';

    protected $description = 'Write the product page views collected in the cache to product.view_count';

    public function handle(ProductViewCounter $views): int
    {
        $written = $views->flush();

        $this->info("Recorded {$written} product views.");

        return self::SUCCESS;
    }
}
