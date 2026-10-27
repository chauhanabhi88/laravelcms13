<?php

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Blog\Models\BlogPost;

/**
 * Translated attributes (title, content, ...) are stored on the current
 * locale's blog_post_translation row by the Translatable trait.
 *
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(4), '.');

        return [
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'image' => null,
            'author' => fake()->name(),
            'post_date' => now()->subDays(fake()->numberBetween(1, 60)),
            'is_featured' => config('core.no'),
            'status' => config('core.enabled'),
            'title' => $title,
            'short_content' => fake()->sentence(12),
            'content' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
            'meta_keywords' => null,
            'meta_description' => fake()->sentence(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => config('core.disabled')]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => config('core.yes')]);
    }

    public function scheduled(): static
    {
        return $this->state(['post_date' => now()->addWeek()]);
    }
}
