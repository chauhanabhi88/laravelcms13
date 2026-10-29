<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\FeeRule;
use Modules\Seller\Models\Seller;

/**
 * A global flat fee charged to the seller.
 *
 * @extends Factory<FeeRule>
 */
class FeeRuleFactory extends Factory
{
    protected $model = FeeRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => FeeRule::CODE_FIXED_CLOSING_FEE,
            'scope' => 'global',
            'catalog_category_id' => null,
            'seller_id' => null,
            'type' => FeeRule::TYPE_FLAT,
            'value' => '10.00',
            'charged_to' => FeeRule::CHARGED_TO_SELLER,
            'status' => config('core.enabled'),
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(['code' => $code]);
    }

    public function forSeller(Seller|int $seller): static
    {
        return $this->state(['scope' => 'seller', 'seller_id' => $seller instanceof Seller ? $seller->id : $seller]);
    }

    public function percent(string $value): static
    {
        return $this->state(['type' => FeeRule::TYPE_PERCENT, 'value' => $value]);
    }
}
