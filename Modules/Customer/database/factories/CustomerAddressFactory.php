<?php

namespace Modules\Customer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Customer\Models\Customer;
use Modules\Customer\Models\CustomerAddress;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * The legacy columns (building, street_name, postal_code) are filled from these by
     * CustomerAddress::saving().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'name' => fake()->name(),
            'phone' => '+919'.fake()->numerify('#########'),
            'address_line1' => fake()->buildingNumber().', '.fake()->streetName(),
            'address_line2' => fake()->streetAddress(),
            'pincode' => fake()->numerify('1#####'),
            'city_name' => fake()->city(),
            'state_name' => fake()->state(),
            'tag' => fake()->randomElement(CustomerAddress::TAGS),
            'is_default_address' => config('customer.is_default_address.no'),
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default_address' => config('customer.is_default_address.yes')]);
    }
}
