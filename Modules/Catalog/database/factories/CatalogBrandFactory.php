<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogBrandTranslation;
use Modules\Catalog\Support\CatalogSettings;

/**
 * CatalogBrand::factory()->create(['name' => 'Loomcraft']);
 * CatalogBrand::factory()->requested(42)->create();
 *
 * @extends Factory<CatalogBrand>
 */
class CatalogBrandFactory extends Factory
{
    protected $model = CatalogBrand::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->unique()->words(2, true)).'-'.fake()->unique()->numberBetween(1000, 999999),
            'is_verified' => config('core.no'),
            'status' => config('core.enabled'),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['is_verified' => config('core.yes')]);
    }

    /**
     * A brand a seller asked for, waiting for admin approval.
     */
    public function requested(int $sellerId = 1): static
    {
        return $this->state(fn (): array => ['requested_by_seller_id' => $sellerId, 'status' => config('core.disabled')]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CatalogBrand $brand): void {
            $existing = CatalogBrandTranslation::query()->where('catalog_brand_id', $brand->id)->get();
            $name = $existing->first()?->name ?? Str::headline(Str::beforeLast($brand->slug, '-'));

            foreach (CatalogSettings::contentLocales() as $locale) {
                if (! $existing->contains('locale', $locale)) {
                    CatalogBrandTranslation::query()->create(['catalog_brand_id' => $brand->id, 'locale' => $locale, 'name' => $name]);
                }
            }

            $brand->load('translations');
        });
    }
}
