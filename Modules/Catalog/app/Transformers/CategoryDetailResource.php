<?php

declare(strict_types=1);

namespace Modules\Catalog\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Support\ResolvedAttribute;

/**
 * One category for the storefront / app: SEO meta, breadcrumbs, direct children,
 * filterable attributes (with the options sellers may use) and the buyer-facing defaults.
 *
 * @mixin CatalogCategory
 */
class CategoryDetailResource extends JsonResource
{
    /**
     * @var list<array<string, mixed>>
     */
    protected array $ancestors = [];

    /**
     * @var list<array<string, mixed>>
     */
    protected array $children = [];

    /**
     * @var list<ResolvedAttribute>
     */
    protected array $filterableAttributes = [];

    /**
     * @var array<string, mixed>
     */
    protected array $defaults = [];

    /**
     * @param  list<array<string, mixed>>  $ancestors
     * @param  list<array<string, mixed>>  $children
     * @param  list<ResolvedAttribute>  $filterableAttributes
     * @param  array<string, mixed>  $defaults
     */
    public function withContext(array $ancestors, array $children, array $filterableAttributes, array $defaults): static
    {
        $this->ancestors = $ancestors;
        $this->children = $children;
        $this->filterableAttributes = $filterableAttributes;
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'depth' => $this->depth,
            'is_leaf' => $this->isLeaf(),
            'image' => self::imageUrl($this->image),
            'banner_image' => self::imageUrl($this->banner_image),
            'icon' => $this->icon,
            'meta' => [
                'title' => $this->meta_title ?: $this->name,
                'description' => $this->meta_description,
                'keywords' => $this->meta_keywords,
            ],
            'breadcrumbs' => array_map(fn (array $node): array => [
                'id' => $node['id'],
                'name' => $node['name'],
                'slug' => $node['slug'],
            ], $this->ancestors),
            'children' => array_map(fn (array $node): array => [
                'id' => $node['id'],
                'name' => $node['name'],
                'slug' => $node['slug'],
                'is_leaf' => $node['is_leaf'] === (int) config('core.yes'),
                'image' => self::imageUrl($node['image'] ?? null),
            ], $this->children),
            'filterable_attributes' => array_map(fn (ResolvedAttribute $attribute): array => [
                'id' => $attribute->attributeId,
                'code' => $attribute->code,
                'name' => $attribute->name,
                'input_type' => $attribute->inputType,
                'is_variant_axis' => $attribute->isVariantAxis,
                'options' => array_map(fn (array $option): array => [
                    'id' => $option['id'],
                    'code' => $option['code'],
                    'name' => $option['name'],
                ], $attribute->options),
            ], $this->filterableAttributes),
            'return_policy' => [
                'type' => $this->defaults['return_type'] ?? null,
                'window_days' => $this->defaults['return_window_days'] ?? null,
            ],
        ];
    }

    public static function imageUrl(?string $image): ?string
    {
        return $image ? getImageUrl(['module' => config('catalog.upload.category'), 'image' => $image]) : null;
    }
}
