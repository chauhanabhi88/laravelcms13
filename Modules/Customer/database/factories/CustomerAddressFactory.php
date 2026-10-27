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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'street_name' => fake()->streetAddress(),
            'building' => fake()->buildingNumber(),
            'unit_no' => (string) fake()->numberBetween(1, 99),
            'postal_code' => fake()->numerify('######'),
            'tag' => fake()->randomElement(['Home', 'Work']),
            'is_default_address' => config('customer.is_default_address.no'),
        ];
    }
}
