<?php

declare(strict_types=1);

namespace Modules\Catalog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryTranslation;
use Modules\Catalog\Support\CatalogSettings;

/**
 * Categories with a correct path / depth / leaf flag and a translation per content locale.
 *
 * CatalogCategory::factory()->create(['name' => 'Ethnic Wear']);
 * CatalogCategory::factory()->child($parent)->create(['name' => 'Kurtis']);
 *
 * "name" lands in the current locale's translation (Translatable) and is copied to the
 * other content locales.
 *
 * @extends Factory<CatalogCategory>
 */
class CatalogCategoryFactory extends Factory
{
    protected $model = CatalogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'slug' => Str::slug(fake()->unique()->words(2, true)).'-'.fake()->unique()->numberBetween(1000, 999999),
            'sort_order' => 0,
            'show_in_menu' => config('core.yes'),
            'status' => config('core.enabled'),
        ];
    }

    public function child(CatalogCategory $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => config('core.disabled')]);
    }

    public function hiddenFromMenu(): static
    {
        return $this->state(fn (): array => ['show_in_menu' => config('core.no')]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CatalogCategory $category): void {
            $parent = $category->parent_id ? CatalogCategory::query()->findOrFail($category->parent_id) : null;

            CatalogCategory::query()->whereKey($category->id)->update([
                'path' => ($parent ? $parent->path : '/').$category->id.'/',
                'depth' => $parent ? $parent->depth + 1 : 0,
            ]);

            if ($parent) {
                CatalogCategory::query()->whereKey($parent->id)->update(['is_leaf' => config('core.no')]);
            }

            $existing = CatalogCategoryTranslation::query()->where('catalog_category_id', $category->id)->get();
            $name = $existing->first()?->name ?? Str::headline(Str::beforeLast($category->slug, '-'));

            foreach (CatalogSettings::contentLocales() as $locale) {
                if (! $existing->contains('locale', $locale)) {
                    CatalogCategoryTranslation::query()->create(['catalog_category_id' => $category->id, 'locale' => $locale, 'name' => $name]);
                }
            }

            $category->refresh()->load('translations');
        });
    }
}
