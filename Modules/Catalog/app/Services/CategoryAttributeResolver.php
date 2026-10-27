<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Catalog\Support\CatalogCache;
use Modules\Catalog\Support\ResolvedAttribute;

/**
 * Effective attributes of a category: the union of the mappings along its path, where a
 * mapping on a deeper category replaces the inherited one for the same attribute. Drives
 * the seller listing form and variant matrix (M05), PLP filters (M06) and PDP specs (M07).
 */
class CategoryAttributeResolver
{
    public function __construct(protected CatalogCache $cache) {}

    /**
     * Cached per category and locale until the catalog changes.
     *
     * @return Collection<int, ResolvedAttribute>
     */
    public function for(CatalogCategory|int $category): Collection
    {
        $category = $category instanceof CatalogCategory ? $category : CatalogCategory::query()->findOrFail($category);

        return $this->cache->remember(
            'attributes.'.$category->id.'.'.app()->getLocale(),
            fn (): Collection => $this->resolve($category),
        );
    }

    /**
     * Uncached resolution straight from the database (never through a cache), so it also
     * validates mapping rows that are not committed yet.
     *
     * @return Collection<int, ResolvedAttribute>
     */
    public function resolve(CatalogCategory $category): Collection
    {
        $pathIds = $category->pathIds() ?: [$category->id];
        $depthById = array_flip($pathIds);

        /** @var array<int, CatalogCategoryAttribute> $effective */
        $effective = CatalogCategoryAttribute::query()
            ->whereIn('catalog_category_id', $pathIds)
            ->get()
            ->sortBy(fn (CatalogCategoryAttribute $mapping): int => $depthById[$mapping->catalog_category_id] ?? 0)
            ->keyBy('attribute_id')
            ->all();

        if ($effective === []) {
            return new Collection;
        }

        $attributes = Attribute::query()
            ->with(['translations', 'attributeOption' => fn ($query) => $query->with('translations')->orderBy('sort_order')->orderBy('id')])
            ->whereIn('id', array_keys($effective))
            ->get()
            ->keyBy('id');

        return collect($effective)
            ->filter(fn (CatalogCategoryAttribute $mapping): bool => $attributes->has($mapping->attribute_id))
            ->map(fn (CatalogCategoryAttribute $mapping): ResolvedAttribute => $this->toResolved($mapping, $attributes->get($mapping->attribute_id), $category->id))
            ->sortBy([
                fn (ResolvedAttribute $a, ResolvedAttribute $b): int => $a->sortOrder <=> $b->sortOrder,
                fn (ResolvedAttribute $a, ResolvedAttribute $b): int => strcasecmp($a->name, $b->name),
            ])
            ->values();
    }

    /**
     * @return Collection<int, ResolvedAttribute>
     */
    public function variantAxes(CatalogCategory|int $category): Collection
    {
        return $this->for($category)->filter(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->values();
    }

    /**
     * @return Collection<int, ResolvedAttribute>
     */
    public function filterable(CatalogCategory|int $category): Collection
    {
        return $this->for($category)->filter(fn (ResolvedAttribute $attribute): bool => $attribute->isFilterable)->values();
    }

    protected function toResolved(CatalogCategoryAttribute $mapping, Attribute $attribute, int $categoryId): ResolvedAttribute
    {
        $allowed = is_array($mapping->allowed_option_ids) && $mapping->allowed_option_ids !== []
            ? array_values(array_map('intval', $mapping->allowed_option_ids))
            : null;

        $options = $attribute->attributeOption
            ->filter(fn (AttributeOption $option): bool => $allowed === null || in_array((int) $option->id, $allowed, true))
            ->map(fn (AttributeOption $option): array => [
                'id' => (int) $option->id,
                'code' => $option->custom_option,
                'name' => (string) ($option->name ?? $option->custom_option),
                'image' => $option->image,
                'sort_order' => (int) $option->sort_order,
            ])
            ->values()
            ->all();

        $yes = (int) config('core.yes');

        return new ResolvedAttribute(
            attributeId: (int) $attribute->id,
            code: (string) $attribute->code,
            name: (string) ($attribute->name ?? $attribute->code),
            inputType: (string) $attribute->input_type,
            isVariantAxis: $mapping->is_variant_axis === $yes,
            isFilterable: $mapping->is_filterable === $yes,
            isRequired: $mapping->is_required === $yes,
            showOnCard: $mapping->show_on_card === $yes,
            sortOrder: $mapping->sort_order,
            allowedOptionIds: $allowed,
            options: $options,
            sourceCategoryId: $mapping->catalog_category_id,
            inherited: $mapping->catalog_category_id !== $categoryId,
        );
    }
}
