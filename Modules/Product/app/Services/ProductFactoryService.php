<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogBrand;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Product\DTO\ImageData;
use Modules\Product\DTO\ProductData;
use Modules\Product\DTO\VariantData;
use Modules\Product\Enums\ProductStatus;
use Modules\Product\Exceptions\InvalidPricing;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttributeValue;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductTranslation;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\ProductVariantAttributeValue;
use Modules\Seller\Models\Seller;

/**
 * Creates and updates a whole product atomically: translations, specification attributes,
 * images and the variant matrix (one variant per combination of the category's variant
 * axes), with opening stock in the inventory ledger. Used by the seller listing form and
 * the bulk importer (M05).
 *
 * On a published (live / paused) product, QC-sensitive edits — content, images, category,
 * brand, tax codes, specification attributes and new variants — are parked in
 * pending_changes for a moderator, while price, stock, variant status and logistics data
 * apply at once (FR-PRD-005). applyPendingChanges() / discardPendingChanges() are called by
 * ProductModerationService.
 *
 * @phpstan-type SpecValue array{option_ids: list<int>, text: string|null}
 * @phpstan-type Context array{seller: Seller, category: CatalogCategory, axes: Collection<int, ResolvedAttribute>, translations: array<string, array<string, mixed>>, attributes: array<int, SpecValue>, brand_id: int|null, hsn_code: string|null, gst_percent: string, search_keywords: string|null}
 */
class ProductFactoryService
{
    /**
     * Product columns a seller may change on a published product without QC.
     */
    public const DIRECT_FIELDS = [
        'country_of_origin',
        'manufacturer_details',
        'packer_details',
        'importer_details',
        'return_window_days',
        'return_type',
        'is_cod_allowed',
        'search_keywords',
    ];

    /**
     * Attribute input types (Attribute module) holding free text; "textbox" is the Attribute
     * module's name, "text" is kept for older mappings.
     */
    public const TEXT_INPUT_TYPES = ['text', 'textbox', 'textarea'];

    /**
     * Option input types taking exactly one option; the others (multiselect, checkbox) take many.
     */
    public const SINGLE_OPTION_INPUT_TYPES = ['select', 'radio'];

    protected const SKU_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._\-\/]*$/';

    public function __construct(
        protected PricingService $pricing,
        protected InventoryService $inventory,
        protected ProductAggregateService $aggregates,
        protected CategoryAttributeResolver $attributeResolver,
        protected CategoryDefaults $categoryDefaults,
        protected ProductImageService $images,
    ) {}

    /**
     * Create a draft product with its variants. Submit it with ProductModerationService.
     *
     * @throws ProductValidationException
     */
    public function create(ProductData $data, ?Model $actor = null): Product
    {
        $context = $this->validate($data);

        $productId = DB::transaction(function () use ($data, $context, $actor): int {
            $product = new Product;
            $product->fill($this->contentColumns($data, $context) + $this->directColumns($data, $context) + [
                'seller_id' => $context['seller']->id,
                'slug' => $this->uniqueSlug((string) $context['translations'][$this->defaultLocale()]['name']),
                'status' => ProductStatus::Draft,
                'has_pending_changes' => config('core.no'),
                'seller_active' => $context['seller']->isActive() ? config('core.yes') : config('core.no'),
            ]);
            $product->save();

            $this->writeTranslations($product, $context['translations']);
            $this->writeAttributes($product, $context['attributes']);
            $this->writeImages($product, array_map(fn (ImageData $image): array => $image->toArray(), $data->images));

            foreach ($data->variants as $index => $variant) {
                $this->createVariant($product, $context['seller'], $variant, $index, $actor);
            }

            $this->aggregates->refresh($product->id);

            return $product->id;
        });

        return $this->load($productId);
    }

    /**
     * Update a product from the seller's full submission. Variants missing from $data are
     * deleted (never-published products) or disabled (published ones keep their history).
     *
     * @throws ProductValidationException
     */
    public function update(Product $product, ProductData $data, ?Model $actor = null): Product
    {
        $context = $this->validate($data, $this->load($product->id));

        DB::transaction(function () use ($product, $data, $context, $actor): void {
            $product = $this->lock($product->id);
            $releasedPaths = [];

            if ($product->isPublished()) {
                $pending = $this->sensitiveChanges($product, $data, $context);
                $previous = $product->pending_changes ?? [];

                $product->fill($this->directColumns($data, $context));
                $this->syncVariants($product, $context['seller'], array_values(array_filter($data->variants, fn (VariantData $variant): bool => $variant->id !== null)), $actor, true);

                $product->pending_changes = $pending === [] ? null : $pending;
                $product->has_pending_changes = $pending === [] ? config('core.no') : config('core.yes');

                if ($pending !== [] && $pending != $previous) {
                    $product->submitted_at = now();
                }

                $releasedPaths = array_diff($this->pendingImagePaths($previous), $this->pendingImagePaths($pending));
            } else {
                $releasedPaths = $this->pendingImagePaths($product->pending_changes ?? []);
                $nameChanged = ($context['translations'][$this->defaultLocale()]['name'] ?? null) !== $product->translations->firstWhere('locale', $this->defaultLocale())?->name;

                $product->fill($this->contentColumns($data, $context) + $this->directColumns($data, $context) + [
                    'pending_changes' => null,
                    'has_pending_changes' => config('core.no'),
                ]);

                if ($nameChanged && $product->published_at === null) {
                    $product->slug = $this->uniqueSlug((string) $context['translations'][$this->defaultLocale()]['name'], $product->id);
                }

                $this->writeTranslations($product, $context['translations']);
                $this->writeAttributes($product, $context['attributes']);
                $releasedPaths = array_merge($releasedPaths, $this->writeImages($product, array_map(fn (ImageData $image): array => $image->toArray(), $data->images)));
                $this->syncVariants($product, $context['seller'], $data->variants, $actor, false);
            }

            $product->save();
            $this->aggregates->refresh($product->id);
            $this->cleanupAfterCommit($releasedPaths);
        });

        return $this->load($product->id);
    }

    /**
     * Write a published product's pending changes into the live content. Runs inside the
     * moderator's transaction, on a locked product.
     *
     * @throws ProductException when a pending variant's SKU or combination was taken meanwhile
     */
    public function applyPendingChanges(Product $product, ?Model $actor = null): void
    {
        $pending = $product->pending_changes ?? [];
        $product->loadMissing(['translations', 'variants', 'images', 'attributeValues']);
        $releasedPaths = [];

        if (isset($pending['translations'])) {
            $this->writeTranslations($product, (array) $pending['translations']);
        }

        foreach (['catalog_category_id', 'catalog_brand_id', 'hsn_code', 'gst_percent'] as $column) {
            if (array_key_exists($column, $pending)) {
                $product->setAttribute($column, $pending[$column]);
            }
        }

        if (isset($pending['attributes'])) {
            $this->writeAttributes($product, $this->specValuesFromArray((array) $pending['attributes']));
        }

        if (isset($pending['images'])) {
            $releasedPaths = $this->writeImages($product, (array) $pending['images']);
        }

        if (! empty($pending['variants'])) {
            $seller = Seller::query()->findOrFail($product->seller_id);
            $position = (int) $product->variants->max('position') + 1;

            foreach ((array) $pending['variants'] as $variant) {
                $variant = VariantData::fromArray((array) $variant);
                $this->assertVariantSlotFree($product, $variant);
                $this->createVariant($product, $seller, $variant, $position++, $actor);
            }
        }

        $product->pending_changes = null;
        $product->has_pending_changes = config('core.no');
        $product->save();

        $this->aggregates->refresh($product->id);
        $this->cleanupAfterCommit($releasedPaths);
    }

    /**
     * Drop a published product's pending changes; the live content stays as it is.
     */
    public function discardPendingChanges(Product $product): void
    {
        $paths = $this->pendingImagePaths($product->pending_changes ?? []);

        $product->pending_changes = null;
        $product->has_pending_changes = config('core.no');
        $product->save();

        $this->cleanupAfterCommit($paths);
    }

    /**
     * The product with everything the services and screens read.
     */
    public function load(int $productId): Product
    {
        return Product::query()
            ->withTrashed()
            ->with(['translations', 'variants.attributeValues', 'images', 'attributeValues'])
            ->findOrFail($productId);
    }

    /**
     * Normalised translations of a product, comparable with a submission.
     *
     * @return array<string, array{name: string, description: string|null, highlights: list<string>|null, meta_title: string|null, meta_description: string|null}>
     */
    public function currentTranslations(Product $product): array
    {
        $current = [];

        foreach ($product->translations as $translation) {
            $current[(string) $translation->locale] = [
                'name' => (string) $translation->name,
                'description' => $translation->description !== null && $translation->description !== '' ? (string) $translation->description : null,
                'highlights' => is_array($translation->highlights) && $translation->highlights !== [] ? array_values(array_map('strval', $translation->highlights)) : null,
                'meta_title' => $translation->meta_title ?: null,
                'meta_description' => $translation->meta_description ?: null,
            ];
        }

        ksort($current);

        return $current;
    }

    /**
     * @return array<int, SpecValue>
     */
    public function currentAttributes(Product $product): array
    {
        $current = [];

        foreach ($product->attributeValues as $value) {
            $current[$value->attribute_id] ??= ['option_ids' => [], 'text' => null];

            if ($value->attribute_option_id !== null) {
                $current[$value->attribute_id]['option_ids'][] = $value->attribute_option_id;
            } else {
                $current[$value->attribute_id]['text'] = $value->value_text;
            }
        }

        foreach ($current as &$value) {
            sort($value['option_ids']);
        }
        unset($value);

        ksort($current);

        return $current;
    }

    /**
     * @return list<array{path: string, attribute_option_id: int|null, alt: string|null, width: int|null, height: int|null, content_hash: string|null}>
     */
    public function currentImages(Product $product): array
    {
        return $product->images->map(fn (ProductImage $image): array => [
            'path' => $image->path,
            'attribute_option_id' => $image->attribute_option_id,
            'alt' => $image->alt,
            'width' => $image->width,
            'height' => $image->height,
            'content_hash' => $image->content_hash,
        ])->values()->all();
    }

    public function defaultLocale(): string
    {
        return (string) (config('translatable.fallback_locale') ?: config('app.fallback_locale', 'en'));
    }

    /**
     * Check a submission against the catalog rules; every error is reported at once.
     *
     * @return Context
     *
     * @throws ProductValidationException
     */
    protected function validate(ProductData $data, ?Product $product = null): array
    {
        $errors = [];

        $seller = Seller::query()->find($data->sellerId);
        if ($seller === null) {
            throw new ProductValidationException(['seller_id' => trans('product::product.validation.seller_missing')]);
        }

        if ($product === null && ! $seller->isActive()) {
            $errors['seller_id'] = trans('product::product.validation.seller_inactive');
        }

        if ($product !== null && $product->seller_id !== $seller->id) {
            $errors['seller_id'] = trans('product::product.validation.seller_mismatch');
        }

        $category = CatalogCategory::query()->find($data->categoryId);
        if ($category === null || ! $category->isEnabled() || ! $category->isLeaf()) {
            throw new ProductValidationException($errors + ['catalog_category_id' => trans('product::product.validation.category_leaf')]);
        }

        $resolved = $this->attributeResolver->for($category);
        $axes = $resolved->filter(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->keyBy('attributeId');
        $specs = $resolved->reject(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->keyBy('attributeId');
        $defaults = $this->categoryDefaults->for($category);

        if ($data->brandId !== null) {
            $brand = CatalogBrand::query()->find($data->brandId);
            $usable = $brand !== null && ((int) $brand->status === (int) config('core.enabled') || $brand->requested_by_seller_id === $seller->id);

            if (! $usable) {
                $errors['catalog_brand_id'] = trans('product::product.validation.brand_invalid');
            }
        }

        $translations = $this->normaliseTranslations($data->translations, $errors);

        if ($data->hsnCode !== null && ! preg_match('/^\d{4,8}$/', $data->hsnCode)) {
            $errors['hsn_code'] = trans('product::product.validation.hsn_code');
        }

        $gstPercent = $data->gstPercent ?? $defaults['gst_percent'];
        if (! is_numeric($gstPercent) || (float) $gstPercent < 0 || (float) $gstPercent > 40 || ! preg_match('/^\d{1,2}(\.\d{1,2})?$/', (string) $gstPercent)) {
            $errors['gst_percent'] = trans('product::product.validation.gst_percent');
            $gstPercent = '0';
        }

        if (! preg_match('/^[A-Z]{2}$/', $data->countryOfOrigin)) {
            $errors['country_of_origin'] = trans('product::product.validation.country');
        }

        if ($data->returnWindowDays !== null && ($data->returnWindowDays < 0 || $data->returnWindowDays > (int) $defaults['return_window_days'])) {
            $errors['return_window_days'] = trans('product::product.validation.return_window', ['max' => $defaults['return_window_days']]);
        }

        if ($data->returnType !== null && ! in_array($data->returnType, (array) config('catalog.return_types'), true)) {
            $errors['return_type'] = trans('product::product.validation.return_type');
        }

        foreach (['manufacturer_details' => $data->manufacturerDetails, 'packer_details' => $data->packerDetails, 'importer_details' => $data->importerDetails] as $field => $party) {
            if ($party !== null && (mb_strlen((string) ($party['name'] ?? '')) > 200 || mb_strlen((string) ($party['address'] ?? '')) > 500)) {
                $errors[$field] = trans('product::product.validation.party');
            }
        }

        $keywords = $this->normaliseKeywords($data->searchKeywords);
        if ($keywords !== null && mb_strlen($keywords) > 500) {
            $errors['search_keywords'] = trans('product::product.validation.keywords');
        }

        $attributes = $this->validateSpecs($data->attributes, $specs, $errors);
        $this->validateImages($data->images, $axes, $errors);
        $this->validateVariants($data->variants, $seller, $category, $axes, $product, $errors);

        if ($errors !== []) {
            throw new ProductValidationException($errors);
        }

        return [
            'seller' => $seller,
            'category' => $category,
            'axes' => $axes,
            'translations' => $translations,
            'attributes' => $attributes,
            'brand_id' => $data->brandId,
            'hsn_code' => $data->hsnCode ?? $defaults['hsn_code'],
            'gst_percent' => number_format((float) $gstPercent, 2, '.', ''),
            'search_keywords' => $keywords,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     * @param  array<string, string>  $errors
     * @return array<string, array{name: string, description: string|null, highlights: list<string>|null, meta_title: string|null, meta_description: string|null}>
     */
    protected function normaliseTranslations(array $translations, array &$errors): array
    {
        $normalised = [];
        $maxHighlights = (int) config('product.max_highlights', 8);
        $maxHighlightLength = (int) config('product.max_highlight_length', 200);
        $text = fn (mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;

        foreach ($translations as $locale => $fields) {
            $locale = (string) $locale;
            $fields = (array) $fields;
            $name = $text($fields['name'] ?? null);

            if ($name === null) {
                continue;
            }

            if (! preg_match('/^[a-z]{2}([-_][A-Za-z]{2})?$/', $locale)) {
                $errors["translations.{$locale}"] = trans('product::product.validation.locale');

                continue;
            }

            $highlights = array_values(array_filter(array_map(fn ($line) => $text($line), (array) ($fields['highlights'] ?? [])), fn (?string $line): bool => $line !== null));

            if (mb_strlen($name) > 200) {
                $errors["translations.{$locale}.name"] = trans('product::product.validation.name_length');
            }

            if (count($highlights) > $maxHighlights) {
                $errors["translations.{$locale}.highlights"] = trans('product::product.validation.highlights_count', ['max' => $maxHighlights]);
            }

            foreach ($highlights as $line) {
                if (mb_strlen($line) > $maxHighlightLength) {
                    $errors["translations.{$locale}.highlights"] = trans('product::product.validation.highlight_length', ['max' => $maxHighlightLength]);
                }
            }

            $description = $text($fields['description'] ?? null);
            if ($description !== null && mb_strlen($description) > 20000) {
                $errors["translations.{$locale}.description"] = trans('product::product.validation.description_length');
            }

            $normalised[$locale] = [
                'name' => $name,
                'description' => $description,
                'highlights' => $highlights === [] ? null : $highlights,
                'meta_title' => $text($fields['meta_title'] ?? null) !== null ? Str::limit((string) $text($fields['meta_title']), 255, '') : null,
                'meta_description' => $text($fields['meta_description'] ?? null) !== null ? Str::limit((string) $text($fields['meta_description']), 500, '') : null,
            ];
        }

        if (! isset($normalised[$this->defaultLocale()])) {
            $errors['translations.'.$this->defaultLocale().'.name'] = trans('product::product.validation.name_required');
        }

        ksort($normalised);

        return $normalised;
    }

    /**
     * @param  array<int, int|list<int>|string|null>  $values
     * @param  Collection<int, ResolvedAttribute>  $specs  keyed by attribute id
     * @param  array<string, string>  $errors
     * @return array<int, SpecValue>
     */
    protected function validateSpecs(array $values, Collection $specs, array &$errors): array
    {
        $normalised = [];

        foreach ($values as $attributeId => $value) {
            $key = "attributes.{$attributeId}";

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            /** @var ResolvedAttribute|null $attribute */
            $attribute = $specs->get((int) $attributeId);
            if ($attribute === null) {
                $errors[$key] = trans('product::product.validation.attribute_not_applicable');

                continue;
            }

            if (in_array($attribute->inputType, self::TEXT_INPUT_TYPES, true)) {
                $text = is_scalar($value) ? trim((string) $value) : '';

                if ($text === '' || mb_strlen($text) > 255) {
                    $errors[$key] = trans('product::product.validation.attribute_text', ['attribute' => $attribute->name]);

                    continue;
                }

                $normalised[(int) $attributeId] = ['option_ids' => [], 'text' => $text];

                continue;
            }

            if ($attribute->inputType === 'boolean') {
                $flag = is_bool($value) ? ($value ? config('core.yes') : config('core.no')) : (int) $value;

                if (! in_array((int) $flag, [(int) config('core.yes'), (int) config('core.no')], true)) {
                    $errors[$key] = trans('product::product.validation.attribute_option', ['attribute' => $attribute->name]);

                    continue;
                }

                $normalised[(int) $attributeId] = ['option_ids' => [], 'text' => (string) (int) $flag];

                continue;
            }

            $optionIds = array_values(array_unique(array_map('intval', (array) $value)));
            sort($optionIds);
            $allowed = array_column($attribute->options, 'id');

            if (in_array($attribute->inputType, self::SINGLE_OPTION_INPUT_TYPES, true) && count($optionIds) > 1) {
                $errors[$key] = trans('product::product.validation.attribute_single', ['attribute' => $attribute->name]);

                continue;
            }

            if (array_diff($optionIds, $allowed) !== []) {
                $errors[$key] = trans('product::product.validation.attribute_option', ['attribute' => $attribute->name]);

                continue;
            }

            $normalised[(int) $attributeId] = ['option_ids' => $optionIds, 'text' => null];
        }

        ksort($normalised);

        return $normalised;
    }

    /**
     * @param  list<ImageData>  $images
     * @param  Collection<int, ResolvedAttribute>  $axes
     * @param  array<string, string>  $errors
     */
    protected function validateImages(array $images, Collection $axes, array &$errors): void
    {
        $axisOptionIds = $axes->flatMap(fn (ResolvedAttribute $axis): array => array_column($axis->options, 'id'))->all();
        $general = 0;
        $perOption = [];

        foreach ($images as $index => $image) {
            if ($image->path === '' || basename($image->path) !== $image->path || mb_strlen($image->path) > 255) {
                $errors["images.{$index}.path"] = trans('product::product.validation.image_path');
            }

            if ($image->attributeOptionId === null) {
                $general++;
            } elseif (! in_array($image->attributeOptionId, $axisOptionIds, true)) {
                $errors["images.{$index}.attribute_option_id"] = trans('product::product.validation.image_option');
            } else {
                $perOption[$image->attributeOptionId] = ($perOption[$image->attributeOptionId] ?? 0) + 1;
            }
        }

        if ($general > (int) config('product.max_images')) {
            $errors['images'] = trans('product::product.validation.image_count', ['max' => config('product.max_images')]);
        }

        if ($perOption !== [] && max($perOption) > (int) config('product.max_images_per_option')) {
            $errors['images'] = trans('product::product.validation.image_option_count', ['max' => config('product.max_images_per_option')]);
        }
    }

    /**
     * @param  list<VariantData>  $variants
     * @param  Collection<int, ResolvedAttribute>  $axes  keyed by attribute id
     * @param  array<string, string>  $errors
     */
    protected function validateVariants(array $variants, Seller $seller, CatalogCategory $category, Collection $axes, ?Product $product, array &$errors): void
    {
        $existing = $product?->variants->keyBy('id') ?? new Collection;
        $published = $product?->isPublished() ?? false;
        $axisIds = $axes->keys()->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $listedIds = array_values(array_filter(array_map(fn (VariantData $variant): ?int => $variant->id, $variants)));

        // Variants left out of the submission that stay on as disabled keep their SKU and
        // combination, so nothing new may reuse them.
        $kept = $existing->filter(fn (ProductVariant $variant): bool => ! in_array($variant->id, $listedIds, true) && $this->survivesRemoval($product, $variant));
        $seenSkus = $kept->mapWithKeys(fn (ProductVariant $variant): array => [strtolower($variant->seller_sku) => true])->all();
        $seenCombinations = [];
        $keptCombinations = $kept->pluck('combination_key')->all();

        foreach ($variants as $index => $variant) {
            $prefix = "variants.{$index}.";
            $current = $variant->id !== null ? $existing->get($variant->id) : null;

            if ($variant->id !== null && $current === null) {
                $errors[$prefix.'id'] = trans('product::product.validation.variant_unknown');

                continue;
            }

            $sku = $variant->sellerSku;
            if ($sku === '' || mb_strlen($sku) > 64 || ! preg_match(self::SKU_PATTERN, $sku)) {
                $errors[$prefix.'seller_sku'] = trans('product::product.validation.seller_sku');
            } elseif (isset($seenSkus[strtolower($sku)])) {
                $errors[$prefix.'seller_sku'] = trans('product::product.validation.seller_sku_duplicate');
            }
            $seenSkus[strtolower($sku)] = true;

            $optionAxisIds = array_map('intval', array_keys($variant->options));
            sort($optionAxisIds);

            if ($optionAxisIds !== $axisIds) {
                $errors[$prefix.'options'] = trans('product::product.validation.variant_axes', ['axes' => $axes->pluck('name')->implode(', ') ?: '-']);
            } else {
                foreach ($variant->options as $attributeId => $optionId) {
                    if (! in_array($optionId, array_column($axes->get($attributeId)->options, 'id'), true)) {
                        $errors[$prefix.'options'] = trans('product::product.validation.variant_option', ['attribute' => $axes->get($attributeId)->name]);
                    }
                }
            }

            $combination = $variant->combinationKey();
            if (isset($seenCombinations[$combination])) {
                $errors[$prefix.'options'] = trans('product::product.validation.variant_combination_duplicate');
            }
            $seenCombinations[$combination] = true;

            if ($current !== null && $published && $current->combination_key !== $combination) {
                $errors[$prefix.'options'] = trans('product::product.validation.variant_combination_locked');
            }

            if ($current === null && in_array($combination, $keptCombinations, true)) {
                $errors[$prefix.'options'] = trans('product::product.validation.variant_combination_exists');
            }

            if ($variant->stock < 0 || $variant->stock > 1000000) {
                $errors[$prefix.'stock'] = trans('product::product.validation.stock');
            } elseif ($current !== null && $variant->stock < $current->reserved) {
                $errors[$prefix.'stock'] = trans('product::product.validation.stock_below_reserved', ['reserved' => $current->reserved]);
            }

            if ($variant->weightGrams < 1 || $variant->weightGrams > 100000) {
                $errors[$prefix.'weight_grams'] = trans('product::product.validation.weight');
            }

            foreach (['length_cm' => $variant->lengthCm, 'breadth_cm' => $variant->breadthCm, 'height_cm' => $variant->heightCm] as $field => $value) {
                if ($value !== null && (! is_numeric($value) || (float) $value <= 0 || (float) $value > 9999.9)) {
                    $errors[$prefix.$field] = trans('product::product.validation.dimension');
                }
            }

            if ($variant->lowStockThreshold !== null && ($variant->lowStockThreshold < 0 || $variant->lowStockThreshold > 100000)) {
                $errors[$prefix.'low_stock_threshold'] = trans('product::product.validation.threshold');
            }

            if ($variant->barcode !== null && ! preg_match('/^[A-Za-z0-9-]{1,32}$/', $variant->barcode)) {
                $errors[$prefix.'barcode'] = trans('product::product.validation.barcode');
            }

            try {
                $this->pricing->validate($variant, $category, $prefix);
            } catch (InvalidPricing $e) {
                $errors[$e->field] = $e->getMessage();
            }
        }

        $skus = array_values(array_unique(array_map(fn (VariantData $variant): string => $variant->sellerSku, $variants)));

        if ($skus !== []) {
            $taken = ProductVariant::query()
                ->where('seller_id', $seller->id)
                ->whereIn('seller_sku', $skus)
                ->when($product !== null, fn ($query) => $query->where('product_id', '!=', $product->id))
                ->pluck('seller_sku')
                ->map(fn (string $sku): string => strtolower($sku))
                ->all();

            foreach ($variants as $index => $variant) {
                if (in_array(strtolower($variant->sellerSku), $taken, true)) {
                    $errors["variants.{$index}.seller_sku"] = trans('product::product.validation.seller_sku_taken');
                }
            }
        }
    }

    /**
     * QC-sensitive differences between a published product and the submission.
     *
     * @param  Context  $context
     * @return array<string, mixed>
     */
    protected function sensitiveChanges(Product $product, ProductData $data, array $context): array
    {
        $changes = [];

        $translations = [];
        $current = $this->currentTranslations($product);
        foreach ($context['translations'] as $locale => $fields) {
            if (($current[$locale] ?? null) !== $fields) {
                $translations[$locale] = $fields;
            }
        }
        if ($translations !== []) {
            $changes['translations'] = $translations;
        }

        $content = $this->contentColumns($data, $context);
        $live = [
            'catalog_category_id' => $product->catalog_category_id,
            'catalog_brand_id' => $product->catalog_brand_id,
            'hsn_code' => $product->hsn_code,
            'gst_percent' => number_format((float) $product->gst_percent, 2, '.', ''),
        ];
        foreach ($content as $column => $value) {
            if ($live[$column] !== $value) {
                $changes[$column] = $value;
            }
        }

        if ($this->currentAttributes($product) !== $context['attributes']) {
            $changes['attributes'] = $context['attributes'];
        }

        $submittedImages = array_map(fn (ImageData $image): array => $image->toArray(), $data->images);
        $identity = fn (array $image): array => [$image['path'], $image['attribute_option_id'], $image['alt']];
        if (array_map($identity, $this->currentImages($product)) !== array_map($identity, $submittedImages)) {
            $changes['images'] = $submittedImages;
        }

        $newVariants = array_values(array_filter($data->variants, fn (VariantData $variant): bool => $variant->id === null));
        if ($newVariants !== []) {
            $changes['variants'] = array_map(fn (VariantData $variant): array => $variant->toArray(), $newVariants);
        }

        return $changes;
    }

    /**
     * Columns that go through QC on a published product.
     *
     * @param  Context  $context
     * @return array{catalog_category_id: int, catalog_brand_id: int|null, hsn_code: string|null, gst_percent: string}
     */
    protected function contentColumns(ProductData $data, array $context): array
    {
        return [
            'catalog_category_id' => $context['category']->id,
            'catalog_brand_id' => $context['brand_id'],
            'hsn_code' => $context['hsn_code'],
            'gst_percent' => $context['gst_percent'],
        ];
    }

    /**
     * Columns a seller may always change directly.
     *
     * @param  Context  $context
     * @return array<string, mixed>
     */
    protected function directColumns(ProductData $data, array $context): array
    {
        return [
            'country_of_origin' => $data->countryOfOrigin,
            'manufacturer_details' => $data->manufacturerDetails,
            'packer_details' => $data->packerDetails,
            'importer_details' => $data->importerDetails,
            'return_window_days' => $data->returnWindowDays,
            'return_type' => $data->returnType,
            'is_cod_allowed' => $data->isCodAllowed ? config('core.yes') : config('core.no'),
            'search_keywords' => $context['search_keywords'],
        ];
    }

    /**
     * Create, update and delete / disable variants so the product matches $variants.
     *
     * @param  list<VariantData>  $variants
     */
    protected function syncVariants(Product $product, Seller $seller, array $variants, ?Model $actor, bool $published): void
    {
        $existing = $product->variants->keyBy('id');
        $listed = array_values(array_filter(array_map(fn (VariantData $variant): ?int => $variant->id, $variants)));

        // Unlisted variants go first, so a new variant may take over a deleted one's
        // combination or seller SKU.
        foreach ($existing as $id => $variant) {
            if (in_array($id, $listed, true)) {
                continue;
            }

            if ($this->survivesRemoval($product, $variant)) {
                if ($variant->isEnabled()) {
                    $variant->update(['status' => config('core.disabled')]);
                }
            } else {
                $variant->delete();
            }
        }

        foreach ($variants as $index => $variant) {
            if ($variant->id === null) {
                $this->createVariant($product, $seller, $variant, $index, $actor);

                continue;
            }

            $this->updateVariant($existing->get($variant->id), $variant, $index, $actor, $published);
        }

        $product->unsetRelation('variants');
    }

    /**
     * A variant left out of a submission is only disabled once the product was published
     * or orders hold its stock; otherwise it is deleted.
     */
    protected function survivesRemoval(Product $product, ProductVariant $variant): bool
    {
        return $product->published_at !== null || $variant->reserved > 0;
    }

    protected function createVariant(Product $product, Seller $seller, VariantData $data, int $index, ?Model $actor): ProductVariant
    {
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'seller_sku' => $data->sellerSku,
            'sku' => $this->nextSku($product, $seller),
            'combination_key' => $data->combinationKey(),
            'mrp' => $data->mrp,
            'price' => $data->price,
            'stock' => $data->stock,
            'reserved' => 0,
            'low_stock_threshold' => $data->lowStockThreshold ?? (int) config('product.default_low_stock_threshold', 5),
            'weight_grams' => $data->weightGrams,
            'length_cm' => $data->lengthCm,
            'breadth_cm' => $data->breadthCm,
            'height_cm' => $data->heightCm,
            'barcode' => $data->barcode,
            'status' => $data->active ? config('core.enabled') : config('core.disabled'),
            'position' => $data->position ?? $index,
        ]);

        $this->writeVariantOptions($variant, $data->options);
        $this->inventory->recordOpening($variant, $actor, trans('product::product.labels.opening_stock_note'));

        return $variant;
    }

    protected function updateVariant(ProductVariant $variant, VariantData $data, int $index, ?Model $actor, bool $published): void
    {
        $variant->fill([
            'seller_sku' => $data->sellerSku,
            'mrp' => $data->mrp,
            'price' => $data->price,
            'low_stock_threshold' => $data->lowStockThreshold ?? $variant->low_stock_threshold,
            'weight_grams' => $data->weightGrams,
            'length_cm' => $data->lengthCm,
            'breadth_cm' => $data->breadthCm,
            'height_cm' => $data->heightCm,
            'barcode' => $data->barcode,
            'status' => $data->active ? config('core.enabled') : config('core.disabled'),
            'position' => $data->position ?? $index,
        ]);

        if (! $published && $variant->combination_key !== $data->combinationKey()) {
            $variant->combination_key = $data->combinationKey();
            $this->writeVariantOptions($variant, $data->options);
        }

        $variant->save();

        // Stock changes are ledgered by InventoryService (locks the row, writes the movement).
        if ($data->stock !== $variant->stock) {
            $this->inventory->adjust($variant->id, $data->stock, $actor, trans('product::product.labels.listing_edit_note'));
        }
    }

    /**
     * @param  array<int, int>  $options  attribute id => option id
     */
    protected function writeVariantOptions(ProductVariant $variant, array $options): void
    {
        ProductVariantAttributeValue::query()->where('variant_id', $variant->id)->delete();

        foreach ($options as $attributeId => $optionId) {
            ProductVariantAttributeValue::query()->insert([
                'variant_id' => $variant->id,
                'attribute_id' => (int) $attributeId,
                'attribute_option_id' => (int) $optionId,
            ]);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations  normalised, locale => fields
     */
    protected function writeTranslations(Product $product, array $translations): void
    {
        foreach ($translations as $locale => $fields) {
            ProductTranslation::query()->updateOrCreate(
                ['product_id' => $product->id, 'locale' => (string) $locale],
                [
                    'name' => (string) $fields['name'],
                    'description' => $fields['description'] ?? null,
                    'highlights' => $fields['highlights'] ?? null,
                    'meta_title' => $fields['meta_title'] ?? null,
                    'meta_description' => $fields['meta_description'] ?? null,
                ],
            );
        }

        $product->unsetRelation('translations');
    }

    /**
     * @param  array<int, SpecValue>  $attributes
     */
    protected function writeAttributes(Product $product, array $attributes): void
    {
        ProductAttributeValue::query()->where('product_id', $product->id)->delete();

        $rows = [];
        foreach ($attributes as $attributeId => $value) {
            foreach ($value['option_ids'] as $optionId) {
                $rows[] = ['product_id' => $product->id, 'attribute_id' => (int) $attributeId, 'attribute_option_id' => (int) $optionId, 'value_text' => null];
            }

            if ($value['text'] !== null) {
                $rows[] = ['product_id' => $product->id, 'attribute_id' => (int) $attributeId, 'attribute_option_id' => null, 'value_text' => $value['text']];
            }
        }

        if ($rows !== []) {
            ProductAttributeValue::query()->insert($rows);
        }

        $product->unsetRelation('attributeValues');
    }

    /**
     * Replace the product's images, in the given order.
     *
     * @param  list<array<string, mixed>>  $images
     * @return list<string> paths the product no longer uses
     */
    protected function writeImages(Product $product, array $images): array
    {
        $previous = ProductImage::query()->where('product_id', $product->id)->pluck('path')->all();
        ProductImage::query()->where('product_id', $product->id)->delete();

        foreach (array_values($images) as $position => $image) {
            $image = ImageData::fromArray($image);

            ProductImage::query()->create([
                'product_id' => $product->id,
                'attribute_option_id' => $image->attributeOptionId,
                'path' => $image->path,
                'position' => $position,
                'width' => $image->width,
                'height' => $image->height,
                'content_hash' => $image->contentHash,
                'alt' => $image->alt,
            ]);
        }

        $product->unsetRelation('images');

        return array_values(array_diff($previous, array_map(fn (array $image): string => (string) ($image['path'] ?? ''), $images)));
    }

    /**
     * A pending new variant still fits: its seller SKU and combination are free.
     */
    protected function assertVariantSlotFree(Product $product, VariantData $variant): void
    {
        $skuTaken = ProductVariant::query()->where('seller_id', $product->seller_id)->where('seller_sku', $variant->sellerSku)->exists();
        $combinationTaken = ProductVariant::query()->where('product_id', $product->id)->where('combination_key', $variant->combinationKey())->exists();

        if ($skuTaken || $combinationTaken) {
            throw ProductException::because('pending_variant_conflict', ['sku' => $variant->sellerSku]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $attributes  pending JSON: attribute id => {option_ids, text}
     * @return array<int, SpecValue>
     */
    protected function specValuesFromArray(array $attributes): array
    {
        $values = [];

        foreach ($attributes as $attributeId => $value) {
            $values[(int) $attributeId] = [
                'option_ids' => array_values(array_map('intval', (array) ($value['option_ids'] ?? []))),
                'text' => isset($value['text']) ? (string) $value['text'] : null,
            ];
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return list<string>
     */
    protected function pendingImagePaths(array $pending): array
    {
        return array_values(array_filter(array_map(fn ($image): string => (string) ($image['path'] ?? ''), (array) ($pending['images'] ?? []))));
    }

    /**
     * @param  list<string>  $paths
     */
    protected function cleanupAfterCommit(array $paths): void
    {
        $paths = array_values(array_unique($paths));

        if ($paths !== []) {
            DB::afterCommit(fn () => $this->images->deleteUnreferenced($paths));
        }
    }

    protected function lock(int $productId): Product
    {
        return Product::query()
            ->withTrashed()
            ->with(['translations', 'variants.attributeValues', 'images', 'attributeValues'])
            ->lockForUpdate()
            ->findOrFail($productId);
    }

    /**
     * System SKU {sellerCode}-{productId}-{n}, n counting up per product.
     */
    protected function nextSku(Product $product, Seller $seller): string
    {
        $prefix = strtoupper($seller->code).'-'.$product->id.'-';

        $last = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('sku', 'like', $prefix.'%')
            ->pluck('sku')
            ->map(fn (string $sku): int => (int) substr($sku, strlen($prefix)))
            ->max();

        return Str::limit($prefix.(((int) $last) + 1), 32, '');
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($name, 150, '')) ?: 'product';
        $slug = $base;

        while (Product::query()->withTrashed()->where('slug', $slug)->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }

    /**
     * Seller tags: lower-cased, trimmed, de-duplicated, comma separated.
     */
    protected function normaliseKeywords(?string $keywords): ?string
    {
        if ($keywords === null) {
            return null;
        }

        $tags = array_values(array_unique(array_filter(array_map(
            fn (string $tag): string => preg_replace('/\s+/', ' ', mb_strtolower(trim($tag))) ?? '',
            explode(',', $keywords),
        ), fn (string $tag): bool => $tag !== '')));

        return $tags === [] ? null : implode(', ', $tags);
    }
}
