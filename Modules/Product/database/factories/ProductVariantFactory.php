<?php

declare(strict_types=1);

namespace Modules\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * ProductVariant::factory()->for($product)->create(['stock' => 3]);
 *
 * The seller follows the product. Variants made here have no axis options and no opening
 * movement; use ProductFactoryService for a real variant matrix.
 *
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'seller_id' => fn (array $attributes): int => (int) Product::query()->withTrashed()->whereKey($attributes['product_id'])->value('seller_id'),
            'seller_sku' => 'SKU-'.Str::upper(Str::random(10)),
            'sku' => 'FX-'.Str::upper(Str::random(12)),
            'combination_key' => 'factory:'.Str::lower(Str::random(12)),
            'mrp' => '999.00',
            'price' => '499.00',
            'stock' => 10,
            'reserved' => 0,
            'low_stock_threshold' => 5,
            'weight_grams' => 300,
            'length_cm' => '30.0',
            'breadth_cm' => '25.0',
            'height_cm' => '3.0',
            'status' => config('core.enabled'),
            'position' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => config('core.disabled')]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['stock' => 0, 'reserved' => 0]);
    }
}
