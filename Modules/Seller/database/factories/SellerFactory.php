<?php

declare(strict_types=1);

namespace Modules\Seller\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Seller\Enums\SellerStatus;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Modules\Seller\Support\Validation\Gstin;

/**
 * Seller::factory()->onboarding()->withOwner()->create();
 * Seller::factory()->active()->withOwner()->create();
 *
 * @extends Factory<Seller>
 */
class SellerFactory extends Factory
{
    protected $model = Seller::class;

    /**
     * A freshly registered seller: no business details yet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'S9'.fake()->unique()->numerify('#####'),
            'phone' => '+919'.fake()->numerify('#########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => SellerStatus::Onboarding,
            'onboarding_step' => 1,
        ];
    }

    public function onboarding(): static
    {
        return $this->state(fn (): array => ['status' => SellerStatus::Onboarding, 'onboarding_step' => 1]);
    }

    /**
     * Business, tax and agreement filled in, as after a completed wizard.
     */
    public function withBusinessDetails(): static
    {
        return $this->state(function (): array {
            $pan = strtoupper(fake()->lexify('???')).'P'.strtoupper(fake()->lexify('?')).fake()->numerify('####').strtoupper(fake()->lexify('?'));
            $name = Str::title(fake()->unique()->words(2, true)).' Traders';

            return [
                'legal_name' => $name,
                'store_name' => $name,
                'store_slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 99999),
                'business_type' => 'proprietorship',
                'pan' => $pan,
                'pan_last4' => substr($pan, -4),
                'gstin' => Gstin::withChecksum('29'.$pan.'1Z'),
                'gst_status' => Seller::GST_UNVERIFIED,
                'agreement_version' => config('seller.agreement_version'),
                'agreement_accepted_at' => now(),
                'agreement_accepted_ip' => '127.0.0.1',
                'onboarding_step' => Seller::STEPS,
            ];
        });
    }

    public function pendingVerification(): static
    {
        return $this->withBusinessDetails()->state(fn (): array => [
            'status' => SellerStatus::PendingVerification,
            'submitted_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->withBusinessDetails()->state(fn (): array => [
            'status' => SellerStatus::Active,
            'submitted_at' => now()->subDay(),
            'verified_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->active()->state(fn (): array => [
            'status' => SellerStatus::Suspended,
            'suspended_reason' => 'Policy review',
            'payout_hold' => config('core.yes'),
        ]);
    }

    /**
     * Adds an owner login (password "Secret#123").
     */
    public function withOwner(array $attributes = []): static
    {
        return $this->has(SellerUser::factory()->state($attributes), 'users');
    }
}
