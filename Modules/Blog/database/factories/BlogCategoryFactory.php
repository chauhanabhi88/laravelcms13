<?php

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Blog\Models\BlogCategory;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(2, true));

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'sort_order' => fake()->numberBetween(1, 100),
            'status' => config('core.enabled'),
            'title' => $title,
            'description' => fake()->sentence(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => config('core.disabled')]);
    }
}
