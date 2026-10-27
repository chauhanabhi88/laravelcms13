<?php

namespace Modules\Blog\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Blog\Models\BlogPostComment;

/**
 * Approved comment. Only the author's display name is exposed - never their email.
 *
 * @mixin BlogPostComment
 */
class BlogCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = trim(($this->customer?->first_name ?? '').' '.($this->customer?->last_name ?? ''));

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'comment' => $this->comment,
            'author' => $name !== '' ? $name : trans('blog::blog_front.labels.anonymous'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
