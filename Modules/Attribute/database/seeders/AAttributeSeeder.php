<?php

namespace Modules\Attribute\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attribute\Models\Attribute;

class AAttributeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
	{
		Attribute::insert([
			[
				"id" => '1',
				"code" => 'as',
				"input_type" => 'boolean',
				"custom_value" => '2',
				"is_required" => '1',
				"created_at" => '2020-12-02 09:20:17',
				"updated_at" => '2020-12-02 09:20:17',
			],
			[
				"id" => '2',
				"code" => 'data-type',
				"input_type" => 'select',
				"custom_value" => '1',
				"is_required" => '1',
				"created_at" => '2020-12-02 09:22:53',
				"updated_at" => '2020-12-02 09:50:23',
			],
			[
				"id" => '3',
				"code" => 'database-operation',
				"input_type" => 'select',
				"custom_value" => '1',
				"is_required" => '1',
				"created_at" => '2020-12-02 11:22:25',
				"updated_at" => '2020-12-02 11:22:25',
			],
			[
				"id" => '4',
				"code" => 'filters',
				"input_type" => 'select',
				"custom_value" => '1',
				"is_required" => '1',
				"created_at" => '2020-12-14 10:33:44',
				"updated_at" => '2020-12-14 10:33:44',
			],
			[
				"id" => '5',
				"code" => 'input-options',
				"input_type" => 'select',
				"custom_value" => '1',
				"is_required" => '1',
				"created_at" => '2020-12-15 10:49:29',
				"updated_at" => '2020-12-15 10:52:39',
			],
			[
				"id" => '6',
				"code" => 'des',
				"input_type" => 'textarea',
				"custom_value" => '2',
				"is_required" => '1',
				"created_at" => '2021-11-17 11:09:19',
				"updated_at" => '2021-11-17 11:09:19',
			],
		]);
    }
}
