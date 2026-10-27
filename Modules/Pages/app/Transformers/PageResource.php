<?php

namespace Modules\Pages\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pages\Models\Pages;

/**
 * @mixin Pages
 */
class PageResource extends JsonResource
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
            'content' => $this->getPageBody(),
            'meta' => [
                'title' => $this->meta_title ?: $this->title,
                'description' => $this->meta_description,
            ],
            'url' => frontRoute('page', [$this->slug]),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
