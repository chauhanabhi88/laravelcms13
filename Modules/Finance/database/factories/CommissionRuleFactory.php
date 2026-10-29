<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Finance\Models\CommissionRule;
use Modules\Seller\Models\Seller;

/**
 * An active percent commission rule in force since last year. States set the scope.
 *
 * @extends Factory<CommissionRule>
 */
class CommissionRuleFactory extends Factory
{
    protected $model = CommissionRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope' => CommissionRule::SCOPE_GLOBAL,
            'catalog_category_id' => null,
            'seller_id' => null,
            'type' => CommissionRule::TYPE_PERCENT,
            'value' => '5.00',
            'applies_to' => CommissionRule::APPLIES_TO_ITEM_PRICE,
            'effective_from' => now()->subYear()->toDateString(),
            'effective_to' => null,
            'status' => config('core.enabled'),
        ];
    }

    public function forCategory(CatalogCategory|int $category): static
    {
        return $this->state(['scope' => CommissionRule::SCOPE_CATEGORY, 'catalog_category_id' => $category instanceof CatalogCategory ? $category->id : $category]);
    }

    public function forSeller(Seller|int $seller): static
    {
        return $this->state(['scope' => CommissionRule::SCOPE_SELLER, 'seller_id' => $seller instanceof Seller ? $seller->id : $seller]);
    }

    public function forSellerCategory(Seller|int $seller, CatalogCategory|int $category): static
    {
        return $this->state([
            'scope' => CommissionRule::SCOPE_SELLER_CATEGORY,
            'seller_id' => $seller instanceof Seller ? $seller->id : $seller,
            'catalog_category_id' => $category instanceof CatalogCategory ? $category->id : $category,
        ]);
    }

    public function percent(string $value): static
    {
        return $this->state(['type' => CommissionRule::TYPE_PERCENT, 'value' => $value]);
    }

    public function flat(string $value): static
    {
        return $this->state(['type' => CommissionRule::TYPE_FLAT_PER_ITEM, 'value' => $value]);
    }

    public function between(string $from, ?string $to): static
    {
        return $this->state(['effective_from' => $from, 'effective_to' => $to]);
    }
}
