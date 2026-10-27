<?php

declare(strict_types=1);

namespace Modules\Catalog\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A node of CategoryTreeService::tree() (an array), with its children.
 *
 * @property array<string, mixed> $resource
 */
class CategoryNodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $node = $this->resource;

        return [
            'id' => $node['id'],
            'name' => $node['name'],
            'slug' => $node['slug'],
            'depth' => $node['depth'],
            'is_leaf' => $node['is_leaf'] === (int) config('core.yes'),
            'image' => CategoryDetailResource::imageUrl($node['image'] ?? null),
            'icon' => $node['icon'] ?? null,
            'children' => self::collection($node['children'] ?? [])->resolve($request),
        ];
    }
}
