<?php

declare(strict_types=1);

namespace Modules\Storefront\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Storefront\Models\ProductCollection;
use Modules\Storefront\Models\ProductCollectionTranslation;

/**
 * ProductCollection::factory()->create(['name' => 'Festive Picks', 'rules' => ['max_price' => 499]]);
 * ProductCollection::factory()->manual([12, 7, 9])->create();
 *
 * @extends Factory<ProductCollection>
 */
class ProductCollectionFactory extends Factory
{
    protected $model = ProductCollection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(2, true)).'-'.fake()->unique()->numberBetween(1000, 999999),
            'type' => ProductCollection::TYPE_RULE,
            'rules' => [],
            'sort' => 'popularity',
            'status' => config('core.enabled'),
        ];
    }

    /**
     * @param  list<int>  $productIds
     */
    public function manual(array $productIds): static
    {
        return $this->state(fn (): array => ['type' => ProductCollection::TYPE_MANUAL, 'product_ids' => $productIds, 'rules' => null]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => config('core.disabled')]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ProductCollection $collection): void {
            $existing = ProductCollectionTranslation::query()->where('product_collection_id', $collection->id)->get();
            $name = $existing->first()?->name ?? Str::headline(Str::beforeLast($collection->slug, '-'));

            foreach (CatalogSettings::contentLocales() as $locale) {
                if (! $existing->contains('locale', $locale)) {
                    ProductCollectionTranslation::query()->create(['product_collection_id' => $collection->id, 'locale' => $locale, 'name' => $name]);
                }
            }

            $collection->load('translations');
        });
    }
}
