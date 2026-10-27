<?php

namespace Modules\Blog\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Blog\Models\BlogCategory;

/**
 * @mixin BlogCategory
 */
class BlogCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'post_count' => $this->when(isset($this->published_posts_count), fn () => (int) $this->published_posts_count),
            'url' => frontRoute('blog.category', [$this->slug]),
        ];
    }
}
