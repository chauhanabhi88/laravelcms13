<?php

declare(strict_types=1);

namespace Modules\Storefront\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Storefront\Models\HomeSection;
use Modules\Storefront\Models\HomeSectionTranslation;

/**
 * HomeSection::factory()->create(['title' => 'Shop by Category']);
 * HomeSection::factory()->ofType('html_block', ['block_code' => 'home_intro'])->create();
 * HomeSection::factory()->disabled()->create();
 *
 * "title" / "subtitle" land in the current locale and are copied to the other content locales.
 *
 * @extends Factory<HomeSection>
 */
class HomeSectionFactory extends Factory
{
    protected $model = HomeSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'trust_strip',
            'config' => [],
            'sort_order' => fake()->numberBetween(1, 500),
            'status' => config('core.enabled'),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function ofType(string $type, array $config = []): static
    {
        return $this->state(fn (): array => ['type' => $type, 'config' => $config]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['status' => config('core.disabled')]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => ['starts_at' => now()->addDay()]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (HomeSection $section): void {
            $existing = HomeSectionTranslation::query()->where('home_section_id', $section->id)->get();
            $source = $existing->first();

            foreach (CatalogSettings::contentLocales() as $locale) {
                if (! $existing->contains('locale', $locale)) {
                    HomeSectionTranslation::query()->create([
                        'home_section_id' => $section->id,
                        'locale' => $locale,
                        'title' => $source?->title,
                        'subtitle' => $source?->subtitle,
                    ]);
                }
            }

            $section->load('translations');
        });
    }
}
