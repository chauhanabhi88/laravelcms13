<?php

declare(strict_types=1);

namespace Modules\Seller\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;

/**
 * SellerWarehouse::factory()->for($seller)->create();
 *
 * @extends Factory<SellerWarehouse>
 */
class SellerWarehouseFactory extends Factory
{
    protected $model = SellerWarehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'name' => 'Main warehouse',
            'contact_name' => fake()->name(),
            'contact_phone' => '+919'.fake()->numerify('#########'),
            'address_line1' => fake()->streetAddress(),
            'pincode' => '560034',
            'city_name' => 'Bengaluru',
            'state_name' => 'Karnataka',
            'is_default' => config('core.yes'),
            'status' => config('core.enabled'),
        ];
    }
}
