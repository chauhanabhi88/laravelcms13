<?php

namespace Modules\Directory\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Directory\Models\DirectoryCurrencySetup;

class DirectoryDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DirectoryCurrencySetup::insert([
            [
                'code' => 'SGD',
                'label' => 'Singapore Dollar',
                'symbol' => '$',
                'is_base_currency' => 1,
                'is_allowed_currency' => 1,
                'is_display_currency' => 1,
            ],
            [
                'code' => 'MYR',
                'label' => 'Malaysian Ringgit',
                'symbol' => 'RM',
                'is_base_currency' => 1,
                'is_allowed_currency' => 1,
                'is_display_currency' => 1,
            ],
            [
                'code' => 'TWD',
                'label' => 'Taiwan Dollar',
                'symbol' => '$',
                'is_base_currency' => 2,
                'is_allowed_currency' => 1,
                'is_display_currency' => 2,
            ],
            [
                'code' => 'INR',
                'label' => 'Indian Rupee',
                'symbol' => 'RS',
                'is_base_currency' => 2,
                'is_allowed_currency' => 1,
                'is_display_currency' => 2,
            ],
            [
                'code' => 'THB',
                'label' => 'Thai Baht',
                'symbol' => '$',
                'is_base_currency' => 2,
                'is_allowed_currency' => 1,
                'is_display_currency' => 2,
            ],
        ]);
    }
}
