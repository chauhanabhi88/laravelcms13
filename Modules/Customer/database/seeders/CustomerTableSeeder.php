<?php

namespace Modules\Customer\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Customer\Models\Customer;

class CustomerTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
	{
		Customer::insert([
			[
				"id" => "1",
				"first_name" => "Test",
				"last_name" => "User",
				"profile_picture" => "KCp3C9mWxOCcD24CPw4l0MSZNZZZ3TEk6ts0mG9t.jpg",
				"email" => "test@mailinator.com",
				"email_verified_at" => null,
				"password" => "$2y$10$MTXvWNwHwtXGVLgkRhvPzOWnXSKaCxX4VeptbXmoIMcApO41jPg0a",
				"contact_number" => "12345678",
				"status" => "2",
				"remember_token" => null,
				"deleted_at" => null,
				"created_at" => "2021-12-01 12:30:18",
				"updated_at" => "2021-12-01 12:44:32",
			],
		]);
    }
}
