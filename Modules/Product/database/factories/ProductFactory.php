<?php

declare(strict_types=1);

namespace Modules\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductTranslation;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAggregateService;
use Modules\Seller\Models\Seller;

/**
 * Products for tests and seeding (they bypass ProductFactoryService's catalog rules).
 *
 * Product::factory()->create(['name' => 'Cotton Kurti']);
 * Product::factory()->live()->withVariants(3)->create();
 * Product::factory()->live()->withVariants()->outOfStock()->create();
 *
 * "name" lands in the default locale's translation and is copied to the other content
 * locales. withVariants() also adds an image, so the product can be submitted.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory()->active(),
            'catalog_category_id' => CatalogCategory::factory(),
            'slug' => Str::slug(fake()->unique()->words(3, true)).'-'.fake()->unique()->numberBetween(1000, 999999),
            'status' => ProductStatus::Draft,
            'has_pending_changes' => config('core.no'),
            'gst_percent' => '5.00',
            'hsn_code' => '6204',
            'country_of_origin' => 'IN',
            'is_cod_allowed' => config('core.yes'),
            'seller_active' => config('core.yes'),
        ];
    }

    public function forSeller(Seller $seller): static
    {
        return $this->state(fn (): array => ['seller_id' => $seller->id]);
    }

    public function inCategory(CatalogCategory $category): static
    {
        return $this->state(fn (): array => ['catalog_category_id' => $category->id]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Draft]);
    }

    public function pendingQc(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::PendingQc, 'submitted_at' => now()]);
    }

    public function live(): static
    {
        return $this->state(fn (): array => [
            'status' => ProductStatus::Live,
            'submitted_at' => now()->subDays(2),
            'published_at' => now()->subDay(),
            'qc_reviewed_at' => now()->subDay(),
        ]);
    }

    public function paused(): static
    {
        return $this->live()->state(fn (): array => ['status' => ProductStatus::Paused]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Rejected, 'qc_notes' => 'Images are blurry or too small']);
    }

    public function blocked(): static
    {
        return $this->live()->state(fn (): array => ['status' => ProductStatus::Blocked, 'qc_notes' => 'Policy review']);
    }

    /**
     * $count enabled variants (₹499, MRP ₹999, 10 in stock unless overridden) and one image.
     *
     * @param  array<string, mixed>  $attributes  variant attributes
     */
    public function withVariants(int $count = 1, array $attributes = []): static
    {
        return $this->afterCreating(function (Product $product) use ($count, $attributes): void {
            for ($index = 1; $index <= $count; $index++) {
                ProductVariant::factory()->create($attributes + [
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'combination_key' => $count === 1 ? ProductVariant::DEFAULT_COMBINATION : 'factory:'.$index,
                    'position' => $index - 1,
                ]);
            }

            if (! ProductImage::query()->where('product_id', $product->id)->exists()) {
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => Str::random(40).'.jpg',
                    'position' => 0,
                    'width' => 800,
                    'height' => 1067,
                    'content_hash' => md5((string) $product->id.Str::random(8)),
                ]);
            }

            app(ProductAggregateService::class)->refresh($product->id);
            $product->refresh();
        });
    }

    /**
     * Every variant at zero stock (stays visible on the storefront as "Out of stock").
     */
    public function outOfStock(): static
    {
        return $this->afterCreating(function (Product $product): void {
            ProductVariant::query()->where('product_id', $product->id)->update(['stock' => 0, 'reserved' => 0]);

            app(ProductAggregateService::class)->refresh($product->id);
            $product->refresh();
        });
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $existing = ProductTranslation::query()->where('product_id', $product->id)->get();
            $name = $existing->first()?->name ?? Str::headline(Str::beforeLast($product->slug, '-'));
            $locales = array_unique(array_merge(CatalogSettings::contentLocales(), [(string) config('translatable.fallback_locale', 'en')]));

            foreach ($locales as $locale) {
                if (! $existing->contains('locale', $locale)) {
                    ProductTranslation::query()->create(['product_id' => $product->id, 'locale' => $locale, 'name' => $name]);
                }
            }

            $product->refresh()->load('translations');
        });
    }
}
