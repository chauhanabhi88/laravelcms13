<?php

declare(strict_types=1);

namespace Modules\Directory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Directory\Models\DirectoryPincode;

/**
 * @extends Factory<DirectoryPincode>
 */
class DirectoryPincodeFactory extends Factory
{
    protected $model = DirectoryPincode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pincode' => (string) fake()->unique()->numberBetween(110001, 855117),
            'office_name' => fake()->unique()->city().' SO',
            'district' => fake()->city(),
            'state_id' => null,
            'city_id' => null,
            'latitude' => fake()->latitude(8, 35),
            'longitude' => fake()->longitude(68, 97),
            'is_serviceable' => config('core.yes'),
            'cod_allowed' => config('core.yes'),
            'zone' => 'roi',
        ];
    }

    public function unserviceable(): static
    {
        return $this->state(fn (): array => ['is_serviceable' => config('core.no'), 'cod_allowed' => config('core.no')]);
    }

    public function withoutCod(): static
    {
        return $this->state(fn (): array => ['cod_allowed' => config('core.no')]);
    }
}
