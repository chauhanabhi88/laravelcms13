<?php

namespace Modules\Blog\Transformers;

use Illuminate\Http\Request;
use Modules\Blog\Models\BlogPost;

/**
 * Full post: list fields plus body and SEO meta.
 *
 * @mixin BlogPost
 */
class BlogPostDetailResource extends BlogPostResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'short_content' => $this->short_content,
            'content' => $this->content,
            'meta' => [
                'title' => $this->title,
                'description' => $this->meta_description,
                'keywords' => $this->meta_keywords,
            ],
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
