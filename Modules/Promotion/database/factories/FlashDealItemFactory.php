<?php

declare(strict_types=1);

namespace Modules\Promotion\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Product\Models\ProductVariant;
use Modules\Promotion\Models\FlashDeal;
use Modules\Promotion\Models\FlashDealItem;

/**
 * A deal row for a variant (pass it with forVariant()): ₹199, no unit cap.
 *
 * @extends Factory<FlashDealItem>
 */
class FlashDealItemFactory extends Factory
{
    protected $model = FlashDealItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flash_deal_id' => FlashDeal::factory(),
            'deal_price' => '199.00',
            'stock_cap' => null,
            'sold' => 0,
        ];
    }

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(['variant_id' => $variant->id, 'product_id' => $variant->product_id]);
    }
}
