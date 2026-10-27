<?php

declare(strict_types=1);

namespace Modules\Seller\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

/**
 * SellerUser::factory()->for($seller)->create();             // owner, password "Secret#123"
 * SellerUser::factory()->for($seller)->role('orders')->create();
 *
 * @extends Factory<SellerUser>
 */
class SellerUserFactory extends Factory
{
    protected $model = SellerUser::class;

    public const PASSWORD = 'Secret#123';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+918'.fake()->unique()->numerify('#########'),
            'phone_verified_at' => now(),
            'password' => self::PASSWORD,
            'role' => SellerUser::ROLE_OWNER,
            'status' => config('core.enabled'),
        ];
    }

    public function role(string $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => config('core.disabled')]);
    }
}
