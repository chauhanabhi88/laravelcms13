<?php

declare(strict_types=1);

namespace Modules\Cart\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Cart\Models\Cart;
use Modules\Customer\Models\Customer;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * A guest cart.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => null,
            'token' => Cart::newToken(),
            'type' => Cart::TYPE_STANDARD,
            'last_activity_at' => now(),
        ];
    }

    public function forCustomer(?Customer $customer = null): static
    {
        return $this->state(fn (): array => ['customer_id' => $customer?->id ?? Customer::factory()]);
    }

    public function buyNow(): static
    {
        return $this->state(['type' => Cart::TYPE_BUY_NOW]);
    }

    public function idleFor(int $hours): static
    {
        return $this->state(fn (): array => ['last_activity_at' => now()->subHours($hours)]);
    }
}
