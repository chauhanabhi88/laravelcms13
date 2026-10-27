<?php

namespace Modules\Customer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Customer\Models\Customer;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * Matches the default customer password policy (8-20 chars, mixed case, digit, symbol).
     */
    public const PASSWORD = 'Secret#123';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(self::PASSWORD),
            'contact_number' => fake()->numerify('##########'),
            'status' => config('customer.enabled_status'),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => config('core.disabled')]);
    }

    /**
     * A verified mobile number (E.164). Random unless one is given.
     */
    public function withPhone(?string $phone = null): static
    {
        return $this->state(fn (): array => [
            'phone' => $phone ?? '+919'.fake()->unique()->numerify('#########'),
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * An account created by mobile OTP: phone only, no name, email or password.
     */
    public function otpOnly(?string $phone = null): static
    {
        return $this->withPhone($phone)->state([
            'first_name' => null,
            'last_name' => null,
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
            'contact_number' => null,
        ]);
    }

    public function reseller(?string $businessName = null): static
    {
        return $this->state(fn (): array => [
            'is_reseller' => config('core.yes'),
            'reseller_business_name' => $businessName ?? fake()->company(),
        ]);
    }
}
