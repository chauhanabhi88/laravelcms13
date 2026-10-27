<?php

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\BlogPost;
use Modules\Blog\Models\BlogPostComment;
use Modules\Customer\Models\Customer;

/**
 * @extends Factory<BlogPostComment>
 */
class BlogPostCommentFactory extends Factory
{
    protected $model = BlogPostComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => BlogPost::factory(),
            'customer_id' => Customer::factory(),
            'admin_id' => 0,
            'subject' => fake()->sentence(4),
            'comment' => fake()->paragraph(),
            'status' => config('blog.comment_status.pending'),
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => config('blog.comment_status.approved')]);
    }
}
