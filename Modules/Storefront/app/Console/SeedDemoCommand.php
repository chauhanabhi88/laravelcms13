<?php

declare(strict_types=1);

namespace Modules\Storefront\Console;

use Illuminate\Console\Command;
use Modules\Storefront\Database\Seeders\MarketplaceDemoSeeder;

/**
 * Fills a non-production database with the client demo marketplace (plan M06 "storefront:seed-demo"):
 * real product photos, suppliers, buyers, orders in every state, reviews, returns, coupons,
 * a flash deal and home banners. Needs the Catalog taxonomy (php artisan module:seed Catalog)
 * and internet access for the first run (photos are cached in storage/app/demo-photos).
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'storefront:seed-demo';

    protected $description = 'Seed the client demo marketplace (not for production)';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('The demo marketplace is not for production.');

            return self::FAILURE;
        }

        $seeder = $this->laravel->make(MarketplaceDemoSeeder::class);
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->__invoke();

        return self::SUCCESS;
    }
}
