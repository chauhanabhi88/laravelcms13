<?php

namespace Modules\Attribute\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attribute\Models\AttributeTranslation;

class BAttributeTranslationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
	{
		AttributeTranslation::insert([
			[
				"id" => '1',
				"attribute_id" => '1',
				"name" => 'as',
				"locale" => 'en',
				"created_at" => '2020-12-02 09:20:17',
				"updated_at" => '2020-12-02 09:20:17',
			],
			[
				"id" => '2',
				"attribute_id" => '2',
				"name" => 'dataType',
				"locale" => 'en',
				"created_at" => '2020-12-02 09:22:53',
				"updated_at" => '2020-12-02 09:22:53',
			],
			[
				"id" => '3',
				"attribute_id" => '3',
				"name" => 'Database Operation',
				"locale" => 'en',
				"created_at" => '2020-12-02 11:22:25',
				"updated_at" => '2020-12-02 11:22:25',
			],
			[
				"id" => '4',
				"attribute_id" => '4',
				"name" => 'Filter Options',
				"locale" => 'en',
				"created_at" => '2020-12-14 10:33:44',
				"updated_at" => '2020-12-14 10:33:44',
			],
			[
				"id" => '5',
				"attribute_id" => '5',
				"name" => 'Input Options',
				"locale" => 'en',
				"created_at" => '2020-12-15 10:49:29',
				"updated_at" => '2020-12-15 10:49:29',
			],
			[
				"id" => '6',
				"attribute_id" => '5',
				"name" => 'Input Options',
				"locale" => 'cn',
				"created_at" => '2021-04-13 06:28:01',
				"updated_at" => '2021-04-13 06:28:01',
			],
			[
				"id" => '7',
				"attribute_id" => '6',
				"name" => 'Enter Description',
				"locale" => 'en',
				"created_at" => '2021-11-17 11:09:19',
				"updated_at" => '2021-11-30 12:23:03',
			],
		]);
    }
}
