<?php

namespace Modules\Pages\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Pages\Models\Pages;

/**
 * @extends Factory<Pages>
 */
class PagesFactory extends Factory
{
    protected $model = Pages::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'status' => config('core.enabled'),
            'title' => $title,
            'body' => '<p>'.fake()->paragraph().'</p>',
            'meta_title' => null,
            'meta_description' => fake()->sentence(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => config('core.disabled')]);
    }
}
