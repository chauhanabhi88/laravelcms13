<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * php artisan module:seed Catalog
 */
class CatalogDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogAttributeSeeder::class,
            CatalogTaxonomySeeder::class,
        ]);
    }
}
