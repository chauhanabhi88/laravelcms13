<?php

declare(strict_types=1);

namespace Modules\Product\Support\Listing;

use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Product\DTO\ProductData;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductFactoryService;

/**
 * A product as the seller last saved it, in ProductData::fromArray() form.
 *
 * ProductFactoryService::update() takes the whole product, so each wizard step (and the bulk
 * importer) starts from this, replaces the part it edits and submits the result. On a
 * published product the pending changes are laid over the live content, so editing one step
 * never discards a change waiting in QC from another step.
 *
 * @phpstan-type DraftArray array<string, mixed>
 */
class ListingDraft
{
    public function __construct(
        protected ProductFactoryService $factory,
        protected CategoryAttributeResolver $attributes,
    ) {}

    /**
     * @return DraftArray
     */
    public function fromProduct(Product $product): array
    {
        $product = $this->factory->load($product->id);
        $pending = $product->isPublished() ? ($product->pending_changes ?? []) : [];

        $translations = $this->factory->currentTranslations($product);
        foreach ((array) ($pending['translations'] ?? []) as $locale => $fields) {
            $translations[(string) $locale] = (array) $fields;
        }

        $categoryId = (int) ($pending['catalog_category_id'] ?? $product->catalog_category_id);

        return [
            'seller_id' => $product->seller_id,
            'catalog_category_id' => $categoryId,
            'catalog_brand_id' => array_key_exists('catalog_brand_id', $pending) ? $pending['catalog_brand_id'] : $product->catalog_brand_id,
            'hsn_code' => array_key_exists('hsn_code', $pending) ? $pending['hsn_code'] : $product->hsn_code,
            'gst_percent' => (string) ($pending['gst_percent'] ?? $product->gst_percent),
            'translations' => $translations,
            'attributes' => $this->specInput(
                isset($pending['attributes']) ? $this->specValues((array) $pending['attributes']) : $this->factory->currentAttributes($product),
                $categoryId,
            ),
            'images' => isset($pending['images']) ? array_values((array) $pending['images']) : $this->factory->currentImages($product),
            'variants' => array_merge(
                $product->variants->map(fn (ProductVariant $variant): array => $this->variantArray($variant))->all(),
                array_values(array_map(fn ($variant): array => (array) $variant + ['id' => null], (array) ($pending['variants'] ?? []))),
            ),
            'country_of_origin' => $product->country_of_origin,
            'manufacturer_details' => $product->manufacturer_details,
            'packer_details' => $product->packer_details,
            'importer_details' => $product->importer_details,
            'return_window_days' => $product->return_window_days,
            'return_type' => $product->return_type,
            'is_cod_allowed' => $product->is_cod_allowed,
            'search_keywords' => $product->search_keywords,
        ];
    }

    /**
     * A first draft for a new listing in $category.
     *
     * @return DraftArray
     */
    public function blank(int $sellerId, CatalogCategory $category): array
    {
        return [
            'seller_id' => $sellerId,
            'catalog_category_id' => $category->id,
            'translations' => [],
            'attributes' => [],
            'images' => [],
            'variants' => [],
            'country_of_origin' => 'IN',
            'is_cod_allowed' => (int) config('core.yes'),
        ];
    }

    /**
     * @param  DraftArray  $draft
     */
    public function toData(array $draft): ProductData
    {
        return ProductData::fromArray($draft);
    }

    /**
     * The draft's variants as the matrix / pricing screens show them: ids, axis options and
     * the editable fields, with "published" marking variants whose options are fixed.
     *
     * @param  DraftArray  $draft
     * @return list<array<string, mixed>>
     */
    public function variantRows(array $draft, bool $published): array
    {
        return array_values(array_map(function (array $variant) use ($published): array {
            $options = [];
            foreach ((array) ($variant['options'] ?? []) as $attributeId => $optionId) {
                $options[(int) $attributeId] = (int) $optionId;
            }

            return [
                'id' => $variant['id'] ?? null,
                'key' => ProductVariant::combinationKeyFor($options),
                'options' => $options,
                'seller_sku' => (string) ($variant['seller_sku'] ?? ''),
                'mrp' => (string) ($variant['mrp'] ?? ''),
                'price' => (string) ($variant['price'] ?? ''),
                'stock' => (int) ($variant['stock'] ?? 0),
                'weight_grams' => (int) ($variant['weight_grams'] ?? 0),
                'length_cm' => $variant['length_cm'] ?? null,
                'breadth_cm' => $variant['breadth_cm'] ?? null,
                'height_cm' => $variant['height_cm'] ?? null,
                'low_stock_threshold' => $variant['low_stock_threshold'] ?? null,
                'status' => (int) ($variant['status'] ?? config('core.enabled')),
                'locked' => $published && ($variant['id'] ?? null) !== null,
            ];
        }, (array) ($draft['variants'] ?? [])));
    }

    /**
     * @return array<string, mixed>
     */
    protected function variantArray(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'seller_sku' => $variant->seller_sku,
            'mrp' => $variant->mrp->toDecimal(),
            'price' => $variant->price->toDecimal(),
            'stock' => $variant->stock,
            'options' => $variant->attributeValues->mapWithKeys(fn ($value): array => [(int) $value->attribute_id => (int) $value->attribute_option_id])->all(),
            'weight_grams' => $variant->weight_grams,
            'length_cm' => $variant->length_cm,
            'breadth_cm' => $variant->breadth_cm,
            'height_cm' => $variant->height_cm,
            'low_stock_threshold' => $variant->low_stock_threshold,
            'barcode' => $variant->barcode,
            'status' => $variant->status,
            'position' => $variant->position,
        ];
    }

    /**
     * Stored specification values ({option_ids, text}) as form input: a string for text and
     * boolean attributes, option ids otherwise. Attributes the category no longer maps are
     * dropped, so an old value cannot block a save.
     *
     * @param  array<int, array{option_ids: list<int>, text: string|null}>  $values
     * @return array<int, int|list<int>|string>
     */
    protected function specInput(array $values, int $categoryId): array
    {
        $specs = $this->attributes->for($categoryId)
            ->reject(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)
            ->keyBy('attributeId');

        $input = [];
        foreach ($values as $attributeId => $value) {
            if (! $specs->has($attributeId)) {
                continue;
            }

            $input[(int) $attributeId] = $value['text'] !== null && $value['option_ids'] === []
                ? (string) $value['text']
                : array_values(array_map('intval', $value['option_ids']));
        }

        return $input;
    }

    /**
     * @param  array<int|string, mixed>  $pending
     * @return array<int, array{option_ids: list<int>, text: string|null}>
     */
    protected function specValues(array $pending): array
    {
        $values = [];

        foreach ($pending as $attributeId => $value) {
            $values[(int) $attributeId] = [
                'option_ids' => array_values(array_map('intval', (array) ($value['option_ids'] ?? []))),
                'text' => isset($value['text']) ? (string) $value['text'] : null,
            ];
        }

        return $values;
    }
}
