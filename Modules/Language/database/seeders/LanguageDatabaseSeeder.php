<?php

namespace Modules\Language\Database\Seeders;

use Modules\Language\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class LanguageDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Language::insert([
            [
                'title' => 'English',
                'locale' => 'en',
                'is_default' => '1',
                'status' => '1',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }
}
