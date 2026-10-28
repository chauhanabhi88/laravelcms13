<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Courier;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    protected $model = Courier::class;

    /**
     * An active fake courier (offline driver).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'fake_'.fake()->unique()->numerify('#####'),
            'name' => 'Fake Courier',
            'driver' => Courier::DRIVER_FAKE,
            'credentials' => null,
            'supports_cod' => 1,
            'supports_reverse' => 1,
            'priority' => 5,
            'status' => 1,
        ];
    }

    public function shiprocket(?string $email = 'api@example.test', ?string $password = 'secret'): static
    {
        return $this->state([
            'code' => 'shiprocket_'.fake()->unique()->numerify('#####'),
            'name' => 'Shiprocket',
            'driver' => Courier::DRIVER_SHIPROCKET,
            'credentials' => $email === null ? null : ['email' => $email, 'password' => $password],
        ]);
    }

    public function manual(): static
    {
        return $this->state(['code' => 'manual_'.fake()->unique()->numerify('#####'), 'name' => 'Manual', 'driver' => Courier::DRIVER_MANUAL]);
    }

    public function withoutCod(): static
    {
        return $this->state(['supports_cod' => 2]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 2]);
    }
}
