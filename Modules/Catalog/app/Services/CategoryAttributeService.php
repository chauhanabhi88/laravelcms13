<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Events\CatalogTreeChanged;
use Modules\Catalog\Exceptions\CatalogException;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogCategoryAttribute;
use Modules\Catalog\Repositories\CatalogCategoryAttributeRepository;
use Modules\Catalog\Support\CatalogSettings;
use Modules\Catalog\Support\ResolvedAttribute;

/**
 * Writes a category's own attribute mapping. The variant-axis limit is checked on the
 * effective (inherited) attributes of the category and of every descendant, inside the
 * transaction, so an over-limit mapping is never committed.
 */
class CategoryAttributeService
{
    public function __construct(
        protected CatalogCategoryAttributeRepository $mappings,
        protected CategoryAttributeResolver $resolver,
        protected CategoryTreeService $tree,
    ) {}

    /**
     * @param  array<int|string, array<string, mixed>>  $rows  attribute id => submitted row
     */
    public function sync(CatalogCategory $category, array $rows): void
    {
        $normalised = [];
        foreach ($rows as $attributeId => $row) {
            $normalised[(int) $attributeId] = $this->normaliseRow((int) $attributeId, (array) $row);
        }

        DB::transaction(function () use ($category, $normalised): void {
            $this->mappings->sync($category->id, $normalised);
            $this->assertAxesWithinLimit($category);
        });

        event(new CatalogTreeChanged($category->id, 'attributes'));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{is_variant_axis: int, is_filterable: int, is_required: int, show_on_card: int, sort_order: int, allowed_option_ids: list<int>|null}
     */
    protected function normaliseRow(int $attributeId, array $row): array
    {
        $yes = (int) config('core.yes');
        $no = (int) config('core.no');
        $flag = fn (string $key): int => ! empty($row[$key]) && (int) $row[$key] === $yes ? $yes : $no;

        $allowed = array_values(array_unique(array_filter(array_map('intval', (array) ($row['allowed_option_ids'] ?? [])))));

        if ($allowed !== []) {
            $valid = AttributeOption::query()->where('attribute_id', $attributeId)->whereIn('id', $allowed)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            sort($valid);
            $allowed = $valid;
        }

        return [
            'is_variant_axis' => $flag('is_variant_axis'),
            'is_filterable' => $flag('is_filterable'),
            'is_required' => $flag('is_required'),
            'show_on_card' => $flag('show_on_card'),
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'allowed_option_ids' => $allowed === [] ? null : $allowed,
        ];
    }

    protected function assertAxesWithinLimit(CatalogCategory $category): void
    {
        $max = CatalogSettings::maxVariantAxes();
        $ids = [$category->id, ...$this->tree->descendantIds($category)];

        // Only categories that carry their own mapping or sit below the changed one matter;
        // checking the whole subtree keeps an inherited axis from overflowing a child's limit.
        $categories = CatalogCategory::query()->with('translations')->whereIn('id', $ids)->get();

        foreach ($categories as $node) {
            $axes = $this->resolver->resolve($node)->filter(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis);

            if ($axes->count() > $max) {
                throw CatalogException::because('too_many_axes', ['max' => $max, 'name' => $node->name]);
            }
        }
    }

    /**
     * Mapping rows to show on the attributes screen: the category's own rows.
     *
     * @return array<int, CatalogCategoryAttribute>
     */
    public function ownRows(CatalogCategory $category): array
    {
        return $this->mappings->forCategory($category->id)->keyBy('attribute_id')->all();
    }
}
