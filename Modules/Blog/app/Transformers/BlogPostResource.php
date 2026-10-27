<?php

namespace Modules\Blog\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Blog\Models\BlogPost;

/**
 * List representation of a published post.
 *
 * @mixin BlogPost
 */
class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        [$cardWidth, $cardHeight] = config('blog.front.image.card');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt((int) config('blog.front.excerpt_length')),
            'image_url' => $this->imageUrl(),
            'thumbnail_url' => $this->imageUrl($cardWidth, $cardHeight),
            'author' => $this->author,
            'published_at' => $this->post_date?->toIso8601String(),
            'is_featured' => $this->isFeatured(),
            'reading_time' => $this->readingTime(),
            'categories' => BlogCategoryResource::collection($this->whenLoaded('categories')),
            'url' => frontRoute('blog.show', [$this->slug]),
        ];
    }
}
