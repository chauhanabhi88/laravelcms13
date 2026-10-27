<?php

declare(strict_types=1);

namespace Modules\Seller\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;

/**
 * SellerBankAccount::factory()->for($seller)->verified()->create();
 *
 * @extends Factory<SellerBankAccount>
 */
class SellerBankAccountFactory extends Factory
{
    protected $model = SellerBankAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->numerify('############');

        return [
            'seller_id' => Seller::factory(),
            'account_holder_name' => fake()->name(),
            'account_number' => $number,
            'account_last4' => substr($number, -4),
            'ifsc' => 'HDFC0001234',
            'bank_name' => 'HDFC Bank',
            'branch' => 'Koramangala',
            'account_type' => 'current',
            'verification_status' => SellerBankAccount::STATUS_PENDING,
            'is_primary' => config('core.yes'),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => SellerBankAccount::STATUS_VERIFIED,
            'verification_method' => SellerBankAccount::METHOD_PENNY_DROP,
            'verified_name' => $attributes['account_holder_name'] ?? null,
            'name_match_score' => 100,
            'verified_at' => now(),
        ]);
    }

    public function manualReview(): static
    {
        return $this->state(fn (): array => [
            'verification_status' => SellerBankAccount::STATUS_MANUAL_REVIEW,
            'verification_method' => SellerBankAccount::METHOD_CHEQUE,
        ]);
    }
}
