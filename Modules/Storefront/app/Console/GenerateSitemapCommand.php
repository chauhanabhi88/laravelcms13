<?php

declare(strict_types=1);

namespace Modules\Storefront\Console;

use Illuminate\Console\Command;
use Modules\Storefront\Services\Seo\SitemapGenerator;

/**
 * Rebuilds the XML sitemaps (scheduled nightly through the Cron module; run once after deploy).
 */
class GenerateSitemapCommand extends Command
{
    protected $signature = 'storefront:sitemap';

    protected $description = 'Write the XML sitemaps (pages, categories, live products, stores, collections)';

    public function handle(SitemapGenerator $generator): int
    {
        foreach ($generator->generate() as $section => $count) {
            $this->line(sprintf('%-12s %d URLs', $section, $count));
        }

        $this->info('Sitemap index: '.route('storefront.sitemap'));

        return self::SUCCESS;
    }
}
