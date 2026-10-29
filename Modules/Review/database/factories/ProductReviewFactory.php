<?php

declare(strict_types=1);

namespace Modules\Review\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Product\Models\Product;
use Modules\Review\Models\ProductReview;
use Modules\Seller\Models\Seller;

/**
 * A published 5-star review of a live product, on a delivered order item of a new customer.
 * No aggregates are recalculated: feature tests that need them go through ReviewService or
 * call ReviewAggregateService::recalculate().
 *
 * @extends Factory<ProductReview>
 */
class ProductReviewFactory extends Factory
{
    protected $model = ProductReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory()->active(),
            'product_id' => fn (array $attributes): int => Product::factory()->live()->create(['seller_id' => $attributes['seller_id']])->id,
            'sales_order_item_id' => function (array $attributes): int {
                $sellerOrder = SellerOrder::factory()->create([
                    'seller_id' => $attributes['seller_id'],
                    'status' => SellerOrder::STATUS_DELIVERED,
                    'delivered_at' => now()->subDays(2),
                ]);

                return SalesOrderItem::factory()->create([
                    'sales_order_id' => $sellerOrder->sales_order_id,
                    'seller_order_id' => $sellerOrder->id,
                    'product_id' => $attributes['product_id'],
                ])->id;
            },
            'sales_order_id' => fn (array $attributes): int => (int) SalesOrderItem::query()->whereKey($attributes['sales_order_item_id'])->value('sales_order_id'),
            'customer_id' => fn (array $attributes): int => (int) SalesOrder::query()->whereKey($attributes['sales_order_id'])->value('customer_id'),
            'variant_id' => null,
            'rating' => 5,
            'title' => 'Lovely fabric',
            'body' => fake()->sentence(12),
            'author_name' => fake()->firstName().' '.strtoupper(fake()->randomLetter()).'.',
            'variant_label' => 'M / Blue',
            'status' => ProductReview::STATUS_PUBLISHED,
            'moderation_flags' => null,
            'has_images' => (int) config('core.no'),
            'is_returned' => (int) config('core.no'),
            'published_at' => now()->subDay(),
        ];
    }

    public function rating(int $stars): static
    {
        return $this->state(['rating' => $stars]);
    }

    public function status(string $status): static
    {
        return $this->state(fn (): array => [
            'status' => $status,
            'published_at' => $status === ProductReview::STATUS_PUBLISHED ? now()->subDay() : null,
        ]);
    }

    public function pending(): static
    {
        return $this->status(ProductReview::STATUS_PENDING);
    }

    /**
     * On the given product (and its seller).
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => ['product_id' => $product->id, 'seller_id' => $product->seller_id]);
    }
}
