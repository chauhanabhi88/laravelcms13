<?php

namespace Modules\User\Database\Seeders;

use Modules\User\Models\User;
use Illuminate\Database\Seeder;

class UserDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
	{
		User::insert([
			[
				"id" => "1",
				"role_id" => "1",
				"name" => "Admin",
				"email" => "admin@gmail.com",
				"email_verified_at" => null,
				"password" => '$2y$10$l3I4AT/nB7xVAFZdhFkLCOKRxN6Njhs1DRd7prsTY8v4HyZgcBGsW',
				"status" => "1",
				"remember_token" => "Op7bvWMNorc5fD031hHvKqhaNtswCUX40G9ov7nVXLLOzwKihLNwUMKyaXqe",
				"deleted_at" => null,
				"created_at" => "2020-10-31 06:09:26",
				"updated_at" => "2021-01-12 12:02:22",
			],
			
		]);
    }
}
