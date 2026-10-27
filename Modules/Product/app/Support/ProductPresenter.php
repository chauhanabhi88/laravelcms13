<?php

declare(strict_types=1);

namespace Modules\Product\Support;

use Modules\Attribute\Models\Attribute;
use Modules\Attribute\Models\AttributeOption;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Services\CategoryTreeService;
use Modules\Core\Support\Money;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductFactoryService;

/**
 * View data for the admin product detail and QC review screens: attribute / option
 * labels, specification rows, variant labels and the pending-changes diff.
 *
 * @phpstan-type Labels array{attributes: array<int, string>, options: array<int, string>}
 * @phpstan-type DiffRow array{label: string, type: string, old: mixed, new: mixed}
 */
class ProductPresenter
{
    public function __construct(
        protected CategoryTreeService $tree,
        protected ProductFactoryService $factory,
    ) {}

    /**
     * Names of every attribute and option the product (and its pending changes) uses.
     *
     * @return Labels
     */
    public function labels(Product $product): array
    {
        $attributeIds = $product->attributeValues->pluck('attribute_id')->all();
        $optionIds = $product->attributeValues->pluck('attribute_option_id')->filter()->all();

        foreach ($product->variants as $variant) {
            foreach ($variant->attributeValues as $value) {
                $attributeIds[] = $value->attribute_id;
                $optionIds[] = $value->attribute_option_id;
            }
        }

        $optionIds = array_merge($optionIds, $product->images->pluck('attribute_option_id')->filter()->all());
        $pending = $product->pending_changes ?? [];

        foreach ((array) ($pending['attributes'] ?? []) as $attributeId => $value) {
            $attributeIds[] = (int) $attributeId;
            $optionIds = array_merge($optionIds, array_map('intval', (array) ($value['option_ids'] ?? [])));
        }

        foreach ((array) ($pending['variants'] ?? []) as $variant) {
            foreach ((array) ($variant['options'] ?? []) as $attributeId => $optionId) {
                $attributeIds[] = (int) $attributeId;
                $optionIds[] = (int) $optionId;
            }
        }

        foreach ((array) ($pending['images'] ?? []) as $image) {
            if (! empty($image['attribute_option_id'])) {
                $optionIds[] = (int) $image['attribute_option_id'];
            }
        }

        $attributeIds = array_values(array_unique(array_filter($attributeIds)));
        $optionIds = array_values(array_unique(array_filter($optionIds)));

        return [
            'attributes' => $attributeIds === [] ? [] : Attribute::query()->with('translations')->whereIn('id', $attributeIds)->get()
                ->mapWithKeys(fn (Attribute $attribute): array => [(int) $attribute->id => (string) ($attribute->name ?? $attribute->code)])->all(),
            'options' => $optionIds === [] ? [] : AttributeOption::query()->with('translations')->whereIn('id', $optionIds)->get()
                ->mapWithKeys(fn (AttributeOption $option): array => [(int) $option->id => (string) ($option->name ?? $option->custom_option)])->all(),
        ];
    }

    /**
     * Specification table rows: "Fabric" => "Cotton, Linen".
     *
     * @param  array<int, array{option_ids: list<int>, text: string|null}>  $values
     * @param  Labels  $labels
     * @return array<string, string>
     */
    public function specRows(array $values, array $labels): array
    {
        $rows = [];

        foreach ($values as $attributeId => $value) {
            $label = $labels['attributes'][$attributeId] ?? '#'.$attributeId;
            $parts = array_map(fn (int $optionId): string => $labels['options'][$optionId] ?? '#'.$optionId, $value['option_ids']);

            if ($value['text'] !== null) {
                $parts[] = $this->textValue($value['text']);
            }

            $rows[$label] = implode(', ', $parts);
        }

        return $rows;
    }

    /**
     * @param  Labels  $labels
     * @return array<string, string>
     */
    public function currentSpecRows(Product $product, array $labels): array
    {
        return $this->specRows($this->factory->currentAttributes($product), $labels);
    }

    /**
     * "Size: M · Colour: Blue" (empty for a product without variant axes).
     *
     * @param  array<int|string, int>  $options  attribute id => option id
     * @param  Labels  $labels
     */
    public function optionsLabel(array $options, array $labels): string
    {
        $parts = [];

        foreach ($options as $attributeId => $optionId) {
            $parts[] = ($labels['attributes'][(int) $attributeId] ?? '#'.$attributeId).': '.($labels['options'][(int) $optionId] ?? '#'.$optionId);
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  Labels  $labels
     */
    public function variantLabel(ProductVariant $variant, array $labels): string
    {
        return $this->optionsLabel($variant->attributeValues->pluck('attribute_option_id', 'attribute_id')->all(), $labels);
    }

    public function categoryLabel(?int $categoryId): string
    {
        return $categoryId ? ($this->tree->breadcrumb($categoryId) ?: '#'.$categoryId) : '';
    }

    /**
     * Old vs new for each pending change of a published product.
     *
     * @param  Labels  $labels
     * @return list<DiffRow>
     */
    public function pendingDiff(Product $product, array $labels): array
    {
        $pending = $product->pending_changes ?? [];
        $rows = [];

        if ($pending === []) {
            return [];
        }

        $current = $this->factory->currentTranslations($product);
        foreach ((array) ($pending['translations'] ?? []) as $locale => $fields) {
            foreach (['name', 'description', 'highlights', 'meta_title', 'meta_description'] as $field) {
                $old = $current[$locale][$field] ?? null;
                $new = $fields[$field] ?? null;

                if ($old !== $new) {
                    $rows[] = [
                        'label' => trans('product::product.labels.'.$field).' ('.strtoupper((string) $locale).')',
                        'type' => $field === 'highlights' ? 'list' : 'text',
                        'old' => $old,
                        'new' => $new,
                    ];
                }
            }
        }

        if (array_key_exists('catalog_category_id', $pending)) {
            $rows[] = ['label' => trans('product::product.labels.category'), 'type' => 'text', 'old' => $this->categoryLabel($product->catalog_category_id), 'new' => $this->categoryLabel((int) $pending['catalog_category_id'])];
        }

        if (array_key_exists('catalog_brand_id', $pending)) {
            $rows[] = ['label' => trans('product::product.labels.brand'), 'type' => 'text', 'old' => $this->brandName($product->catalog_brand_id), 'new' => $this->brandName($pending['catalog_brand_id'] !== null ? (int) $pending['catalog_brand_id'] : null)];
        }

        foreach (['hsn_code', 'gst_percent'] as $column) {
            if (array_key_exists($column, $pending)) {
                $rows[] = ['label' => trans('product::product.labels.'.$column), 'type' => 'text', 'old' => (string) $product->getAttribute($column), 'new' => (string) $pending[$column]];
            }
        }

        if (isset($pending['attributes'])) {
            $new = [];
            foreach ((array) $pending['attributes'] as $attributeId => $value) {
                $new[(int) $attributeId] = ['option_ids' => array_map('intval', (array) ($value['option_ids'] ?? [])), 'text' => $value['text'] ?? null];
            }

            $rows[] = ['label' => trans('product::product.labels.attributes'), 'type' => 'specs', 'old' => $this->currentSpecRows($product, $labels), 'new' => $this->specRows($new, $labels)];
        }

        if (isset($pending['images'])) {
            $rows[] = [
                'label' => trans('product::product.labels.images'),
                'type' => 'images',
                'old' => $product->images->map(fn (ProductImage $image): array => ['path' => $image->path, 'url' => $image->url(), 'option' => $labels['options'][$image->attribute_option_id] ?? null])->all(),
                'new' => array_map(fn (array $image): array => [
                    'path' => (string) $image['path'],
                    'url' => getImageUrl(['module' => (string) config('product.upload.folder'), 'image' => (string) $image['path'], 'defualt-image' => true]),
                    'option' => isset($image['attribute_option_id']) ? ($labels['options'][(int) $image['attribute_option_id']] ?? null) : null,
                ], (array) $pending['images']),
            ];
        }

        if (! empty($pending['variants'])) {
            $rows[] = [
                'label' => trans('product::product.labels.new_variants'),
                'type' => 'variants',
                'old' => null,
                'new' => array_map(fn (array $variant): array => [
                    'seller_sku' => (string) $variant['seller_sku'],
                    'options' => $this->optionsLabel((array) ($variant['options'] ?? []), $labels),
                    'mrp' => Money::of((string) $variant['mrp'])->format(true),
                    'price' => Money::of((string) $variant['price'])->format(true),
                    'stock' => (int) $variant['stock'],
                ], (array) $pending['variants']),
            ];
        }

        return $rows;
    }

    protected function brandName(?int $brandId): string
    {
        if ($brandId === null) {
            return '';
        }

        return (string) (CatalogBrand::query()->with('translations')->find($brandId)?->name ?? '#'.$brandId);
    }

    protected function textValue(string $text): string
    {
        return match ($text) {
            (string) config('core.yes') => trans('product::product.labels.yes'),
            (string) config('core.no') => trans('product::product.labels.no'),
            default => $text,
        };
    }
}
