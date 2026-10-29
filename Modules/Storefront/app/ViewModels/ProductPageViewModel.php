<?php

declare(strict_types=1);

namespace Modules\Storefront\ViewModels;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Models\CatalogSizeChart;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Core\Support\Money;
use Modules\Product\Contracts\PriceResolver;
use Modules\Product\DTO\PriceOverride;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttributeValue;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Seller\Models\Seller;
use Modules\Storefront\Contracts\CartEntryPoint;
use Modules\Storefront\Contracts\ProductSearchEngine;
use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\Contracts\WishlistStateProvider;
use Modules\Storefront\DTO\ReviewSummary;
use Modules\Storefront\Services\Search\ListingQuery;
use Modules\Storefront\Support\ReturnPolicy;
use Modules\Storefront\Support\StorefrontSettings;
use Modules\Storefront\Support\StorefrontUrls;

/**
 * Everything the product page (FR-PDP-001 … 011) and the product API show, batch-loaded in a
 * fixed number of queries whatever the number of variants, images or specs: product +
 * translations, enabled variants + their axis options, images, spec values, attribute and
 * option names, the category trail, brand, seller and size chart.
 *
 * $page = app(ProductPageViewModel::class)->load($product, requestedVariantId: 12);
 * $page->payload(); // window.__pdp for the variant picker and gallery
 *
 * @phpstan-type Image array{id: int, src: string|null, zoom: string|null, thumb: string|null, alt: string, width: int|null, height: int|null}
 * @phpstan-type AxisOption array{id: int, label: string, hex: string|null, image: string|null}
 * @phpstan-type Axis array{attributeId: int, code: string, name: string, type: string, options: list<AxisOption>}
 * @phpstan-type VariantRow array{id: int, sku: string, options: object, price: string, mrp: string, priceText: string, mrpText: string, discount: int, available: int, imagesKey: string|null}
 */
class ProductPageViewModel
{
    public const AXIS_SWATCH = 'swatch';

    public const AXIS_BUTTON = 'button';

    public Product $product;

    public ?Seller $seller = null;

    public ?CatalogCategory $category = null;

    /**
     * Enabled ancestors and the leaf, root first.
     *
     * @var list<array{id: int, name: string, slug: string, url: string}>
     */
    public array $categoryTrail = [];

    /**
     * @var array{id: int, name: string, slug: string, verified: bool, size_chart_id: int|null}|null
     */
    public ?array $brand = null;

    /**
     * @var EloquentCollection<int, ProductVariant>
     */
    public EloquentCollection $variantModels;

    /**
     * @var list<Axis>
     */
    public array $axes = [];

    /**
     * @var list<VariantRow>
     */
    public array $variants = [];

    /**
     * "default" => general images, "<optionId>" => that colour's images.
     *
     * @var array<string, list<Image>>
     */
    public array $images = [];

    /**
     * @var list<array{name: string, value: string}>
     */
    public array $specs = [];

    public ?CatalogSizeChart $sizeChart = null;

    public ReturnPolicy $returnPolicy;

    public ReviewSummary $reviews;

    public ?ProductVariant $selectedVariant = null;

    /**
     * Flash-deal prices of the enabled variants (PriceResolver, M18): variant id => override.
     *
     * @var array<int, PriceOverride>
     */
    public array $priceOverrides = [];

    public int $sellerProductCount = 0;

    public bool $preview = false;

    /**
     * @var array<string, mixed> CategoryDefaults::for()
     */
    public array $categoryDefaults = [];

    public function __construct(
        protected CategoryDefaults $defaults,
        protected ProductSearchEngine $search,
        protected ReviewSummaryProvider $reviewProvider,
        protected WishlistStateProvider $wishlist,
        protected CartEntryPoint $cart,
        protected PriceResolver $prices,
    ) {}

    public function load(Product $product, ?int $requestedVariantId = null, bool $preview = false): static
    {
        $product->loadMissing('translations');

        $this->product = $product;
        $this->preview = $preview;

        $this->variantModels = ProductVariant::query()
            ->with('attributeValues')
            ->where('product_id', $product->id)
            ->where('status', config('core.enabled'))
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $this->priceOverrides = $this->overridesFor($this->variantModels);

        $imageRows = ProductImage::query()->where('product_id', $product->id)->orderBy('position')->orderBy('id')->get();
        $specRows = ProductAttributeValue::query()->where('product_id', $product->id)->orderBy('id')->get();

        $axisValues = $this->variantModels->flatMap->attributeValues;
        $attributes = $this->attributeNames(array_merge($axisValues->pluck('attribute_id')->all(), $specRows->pluck('attribute_id')->all()));
        $options = $this->optionNames(array_merge(
            $axisValues->pluck('attribute_option_id')->all(),
            $specRows->pluck('attribute_option_id')->filter()->all(),
            $imageRows->pluck('attribute_option_id')->filter()->all(),
        ));

        $this->images = $this->buildImages($imageRows);
        $this->axes = $this->buildAxes($axisValues, $attributes, $options);
        $this->variants = $this->variantModels->map(fn (ProductVariant $variant): array => $this->variantRow($variant, true))->values()->all();
        $this->specs = $this->buildSpecs($specRows, $attributes, $options);

        $this->loadCategory();
        $this->loadBrand();
        $this->seller = Seller::query()->find($product->seller_id, ['id', 'code', 'legal_name', 'store_name', 'store_slug', 'logo', 'status', 'rating_avg', 'rating_count', 'follower_count', 'verified_at']);

        $this->returnPolicy = ReturnPolicy::for($product, $this->categoryDefaults);
        $this->sizeChart = $this->loadSizeChart();
        $this->reviews = $this->reviewProvider->forProduct($product->id);
        $this->selectedVariant = $this->resolveSelectedVariant($requestedVariantId);
        $this->sellerProductCount = $this->search->count(new ListingQuery(sellerId: $product->seller_id));

        return $this;
    }

    /**
     * Fresh price and availability of the enabled variants (no images), for the
     * storefront.product.availability endpoint.
     *
     * @return list<array<string, mixed>>
     */
    public function availability(Product $product): array
    {
        $variants = ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('status', config('core.enabled'))
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'sku', 'mrp', 'price', 'stock', 'reserved']);
        $this->priceOverrides = $this->overridesFor($variants);

        return $variants
            ->map(fn (ProductVariant $variant): array => $this->variantRow($variant, false))
            ->values()
            ->all();
    }

    /**
     * window.__pdp: axes, variants (available capped, stock never exposed), images per
     * colour, the preselected variant and the endpoints the page scripts use.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'productId' => $this->product->id,
            'name' => $this->name(),
            'axes' => $this->axes,
            'variants' => $this->variants,
            'images' => (object) $this->images,
            'defaultVariantId' => $this->selectedVariant?->id,
            'lowStockThreshold' => StorefrontSettings::lowStockDisplayThreshold(),
            'price' => $this->displayPrice()->toDecimal(),
            'priceText' => $this->displayPrice()->format(),
            'mrpText' => $this->displayMrp()->format(),
            'url' => $this->canonicalUrl(),
            'availabilityUrl' => frontRoute('storefront.product.availability', [$this->product->id]),
            'shareImages' => $this->allImageUrls(),
            'shareSizes' => $this->availableSizeLabels(),
        ];
    }

    public function name(): string
    {
        return (string) ($this->product->name ?? Str::headline($this->product->slug));
    }

    public function canonicalUrl(): string
    {
        return StorefrontUrls::product($this->product->slug, $this->product->id);
    }

    /**
     * The selected variant, else the cheapest enabled one.
     */
    public function priceVariant(): ?ProductVariant
    {
        return $this->selectedVariant ?? $this->variantModels->sortBy(fn (ProductVariant $variant): int => $this->priceOf($variant)->paise)->first();
    }

    /**
     * What the variant sells at right now: a flash-deal price (M18), else its own price.
     */
    public function priceOf(ProductVariant $variant): Money
    {
        return $this->priceOverrides[$variant->id]->price ?? $variant->price;
    }

    /**
     * When the flash deal on the shown variant ends (null without a deal).
     */
    public function dealEndsAt(): ?CarbonInterface
    {
        $variant = $this->priceVariant();

        return $variant !== null ? ($this->priceOverrides[$variant->id]->endsAt ?? null) : null;
    }

    public function displayPrice(): Money
    {
        $variant = $this->priceVariant();

        return $variant !== null ? $this->priceOf($variant) : $this->product->min_price;
    }

    public function displayMrp(): Money
    {
        return $this->priceVariant()?->mrp ?? $this->product->min_mrp;
    }

    public function lowestPrice(): Money
    {
        return $this->variantModels->isEmpty() ? $this->product->min_price : $this->priceOf($this->variantModels->sortBy(fn (ProductVariant $variant): int => $this->priceOf($variant)->paise)->first());
    }

    public function highestPrice(): Money
    {
        return $this->variantModels->isEmpty() ? $this->product->max_price : $this->priceOf($this->variantModels->sortByDesc(fn (ProductVariant $variant): int => $this->priceOf($variant)->paise)->first());
    }

    public function isInStock(): bool
    {
        return $this->variantModels->contains(fn (ProductVariant $variant): bool => $variant->available > 0);
    }

    /**
     * "Only N left" count for the selected variant (null when not low).
     */
    public function lowStockCount(): ?int
    {
        $available = $this->selectedVariant?->available;

        return $available !== null && $available > 0 && $available <= StorefrontSettings::lowStockDisplayThreshold() ? $available : null;
    }

    public function hasPicker(): bool
    {
        return $this->axes !== [] && count($this->variants) > 1;
    }

    /**
     * The option selected on each axis (attribute id => option id) for the server render.
     *
     * @return array<int, int>
     */
    public function selectedOptions(): array
    {
        return $this->selectedVariant === null ? [] : $this->variantOptions($this->selectedVariant);
    }

    /**
     * The images shown first: the selected colour's, else the general ones.
     *
     * @return list<Image>
     */
    public function initialImages(): array
    {
        return $this->images[$this->initialImageKey()] ?? [];
    }

    /**
     * Key of the image set shown first ("default" or the selected colour's option id).
     */
    public function initialImageKey(): string
    {
        $key = $this->selectedVariant !== null ? $this->imagesKey($this->variantOptions($this->selectedVariant)) : null;

        return $key ?? 'default';
    }

    /**
     * The product's in-stock options of the first button axis (sizes), for Share & Earn.
     *
     * @return list<string>
     */
    public function availableSizeLabels(): array
    {
        foreach ($this->axes as $axis) {
            if ($axis['type'] !== self::AXIS_BUTTON) {
                continue;
            }

            return collect($axis['options'])
                ->filter(fn (array $option): bool => collect($this->variants)->contains(
                    fn (array $variant): bool => $variant['available'] > 0 && ((array) $variant['options'])[(string) $axis['attributeId']] === (string) $option['id'],
                ))
                ->pluck('label')
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function highlights(): array
    {
        return array_values(array_filter(array_map(fn ($line): string => trim((string) $line), (array) ($this->product->highlights ?? [])), 'strlen'));
    }

    public function description(): ?string
    {
        $description = trim((string) $this->product->description);

        return $description !== '' ? $description : null;
    }

    /**
     * Legal metrology & origin rows: [label, value].
     *
     * @return list<array{label: string, value: string}>
     */
    public function manufacturerRows(): array
    {
        $rows = [];
        foreach (['manufacturer' => $this->product->manufacturer_details, 'packer' => $this->product->packer_details, 'importer' => $this->product->importer_details] as $key => $details) {
            $value = collect([$details['name'] ?? null, $details['address'] ?? null])->filter(fn ($part): bool => is_string($part) && trim($part) !== '')->implode(', ');

            if ($value !== '') {
                $rows[] = ['label' => trans('storefront::product.manufacturer.'.$key), 'value' => $value];
            }
        }

        $rows[] = ['label' => trans('storefront::product.manufacturer.country_of_origin'), 'value' => $this->countryOfOrigin()];

        return $rows;
    }

    public function countryOfOrigin(): string
    {
        $code = strtoupper((string) $this->product->country_of_origin);

        if (class_exists(\Locale::class) && $code !== '') {
            $name = \Locale::getDisplayRegion('-'.$code, app()->getLocale());

            if (is_string($name) && $name !== '' && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }

    /**
     * [label => url] for <x-theme::breadcrumbs>, ending with the product name.
     *
     * @return array<string, string|null>
     */
    public function breadcrumbs(): array
    {
        $items = [];
        foreach ($this->categoryTrail as $category) {
            $items[$category['name']] = $category['url'];
        }
        $items[$this->name()] = null;

        return $items;
    }

    public function metaTitle(): string
    {
        $title = trim((string) $this->product->meta_title);

        return $title !== '' ? $title : trans('storefront::product.meta.title', [
            'name' => $this->name(),
            'price' => $this->displayPrice()->format(),
            'app' => config('app.name'),
        ]);
    }

    public function metaDescription(): string
    {
        $description = trim((string) $this->product->meta_description);

        if ($description !== '') {
            return $description;
        }

        $highlights = $this->highlights();

        return $highlights !== [] ? implode('. ', $highlights) : Str::limit(strip_tags((string) $this->description()), 160);
    }

    public function ogImage(): ?string
    {
        return $this->initialImages()[0]['src'] ?? StorefrontUrls::productImage($this->product->primary_image, 'pdp');
    }

    /**
     * Every image URL at product page size, de-duplicated (JSON-LD, Share & Earn).
     *
     * @return list<string>
     */
    public function allImageUrls(): array
    {
        return collect($this->images)->flatten(1)->pluck('src')->filter()->unique()->values()->all();
    }

    /**
     * Sold-by card data (null when the seller row is gone).
     *
     * @return array{name: string, url: string|null, logo: string|null, initial: string, rating: float, rating_count: int, followers: int, products: int}|null
     */
    public function sellerCard(): ?array
    {
        if ($this->seller === null) {
            return null;
        }

        $name = $this->seller->displayName();

        return [
            'name' => $name,
            'url' => $this->seller->store_slug ? StorefrontUrls::store($this->seller->store_slug) : null,
            'logo' => $this->seller->logo ? Storage::disk('public')->url($this->seller->logo) : null,
            'initial' => Str::upper(Str::substr($name, 0, 1)),
            'rating' => (float) $this->seller->rating_avg,
            'rating_count' => (int) $this->seller->rating_count,
            'followers' => (int) $this->seller->follower_count,
            'products' => $this->sellerProductCount,
        ];
    }

    public function isWishlisted(): bool
    {
        return $this->wishlist->isWishlisted($this->product->id);
    }

    public function wishlistToggleUrl(): ?string
    {
        return $this->wishlist->toggleUrl();
    }

    public function cart(): CartEntryPoint
    {
        return $this->cart;
    }

    /**
     * Size-chart axis: the first button axis (sizes), when a size chart exists.
     */
    public function sizeAxisId(): ?int
    {
        if ($this->sizeChart === null) {
            return null;
        }

        foreach ($this->axes as $axis) {
            if ($axis['type'] === self::AXIS_BUTTON) {
                return $axis['attributeId'];
            }
        }

        return null;
    }

    /**
     * @return VariantRow|array<string, mixed>
     */
    protected function variantRow(ProductVariant $variant, bool $withOptions): array
    {
        $price = $this->priceOf($variant);
        $row = [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'price' => $price->toDecimal(),
            'mrp' => $variant->mrp->toDecimal(),
            'priceText' => $price->format(),
            'mrpText' => $variant->mrp->format(),
            'discount' => self::discountPercent($price, $variant->mrp),
            'available' => min($variant->available, (int) config('storefront.pdp.max_available_shown', 10)),
        ];

        if ($withOptions) {
            $options = $this->variantOptions($variant);
            $row['options'] = (object) array_combine(array_map('strval', array_keys($options)), array_map('strval', array_values($options)));
            $row['imagesKey'] = $this->imagesKey($options);
        }

        return $row;
    }

    /**
     * Overrides that are really below each variant's own price.
     *
     * @param  EloquentCollection<int, ProductVariant>  $variants
     * @return array<int, PriceOverride>
     */
    protected function overridesFor(EloquentCollection $variants): array
    {
        if ($variants->isEmpty()) {
            return [];
        }

        $byId = $variants->keyBy('id');

        return array_filter(
            $this->prices->forVariants($byId->keys()->all()),
            fn (PriceOverride $override, int $variantId): bool => $byId->has($variantId) && $override->price->compare($byId->get($variantId)->price) < 0,
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public static function discountPercent(Money $price, Money $mrp): int
    {
        return $mrp->isPositive() && $mrp->compare($price) > 0 ? intdiv(($mrp->paise - $price->paise) * 100, $mrp->paise) : 0;
    }

    /**
     * @return array<int, int> attribute id => option id
     */
    protected function variantOptions(ProductVariant $variant): array
    {
        $options = [];
        foreach ($variant->attributeValues as $value) {
            $options[$value->attribute_id] = $value->attribute_option_id;
        }
        ksort($options);

        return $options;
    }

    /**
     * The first of the variant's options that has its own images (its colour).
     *
     * @param  array<int, int>  $options
     */
    protected function imagesKey(array $options): ?string
    {
        foreach ($options as $optionId) {
            if (isset($this->images[(string) $optionId])) {
                return (string) $optionId;
            }
        }

        return null;
    }

    protected function resolveSelectedVariant(?int $requestedVariantId): ?ProductVariant
    {
        if ($requestedVariantId !== null && ($variant = $this->variantModels->firstWhere('id', $requestedVariantId)) !== null) {
            return $variant;
        }

        return $this->variantModels->count() === 1 ? $this->variantModels->first() : null;
    }

    /**
     * @param  EloquentCollection<int, ProductImage>  $rows
     * @return array<string, list<Image>>
     */
    protected function buildImages(EloquentCollection $rows): array
    {
        $name = $this->name();
        $toImage = fn (ProductImage $image): array => [
            'id' => $image->id,
            'src' => StorefrontUrls::productImage($image->path, 'pdp'),
            'zoom' => StorefrontUrls::productImage($image->path, 'zoom'),
            'thumb' => StorefrontUrls::productImage($image->path, 'thumb'),
            'alt' => (string) ($image->alt ?: $name),
            'width' => $image->width,
            'height' => $image->height,
        ];

        $images = [];
        foreach ($rows->groupBy(fn (ProductImage $image): string => $image->attribute_option_id === null ? 'default' : (string) $image->attribute_option_id) as $key => $group) {
            // Rows whose file is missing would render broken images.
            $set = $group->map($toImage)->filter(fn (array $image): bool => $image['src'] !== null)->values()->all();

            if ($set !== []) {
                $images[(string) $key] = $set;
            }
        }

        if (! isset($images['default']) && $images !== []) {
            $images['default'] = reset($images);
        }

        return $images;
    }

    /**
     * @param  Collection<int, mixed>  $axisValues
     * @param  array<int, array{code: string, input_type: string, name: string}>  $attributes
     * @param  array<int, array{attribute_id: int, code: string|null, name: string, sort_order: int}>  $options
     * @return list<Axis>
     */
    protected function buildAxes(Collection $axisValues, array $attributes, array $options): array
    {
        $swatchCodes = array_map('strtolower', (array) config('storefront.pdp.swatch_attribute_codes', []));
        $axes = [];

        foreach ($axisValues->groupBy('attribute_id') as $attributeId => $values) {
            $attributeId = (int) $attributeId;
            $optionIds = $values->pluck('attribute_option_id')->map(fn ($id): int => (int) $id)->unique()->all();

            $axisOptions = collect($optionIds)
                ->map(fn (int $optionId): array => [
                    'id' => $optionId,
                    'label' => $options[$optionId]['name'] ?? (string) $optionId,
                    'hex' => self::hex($options[$optionId]['code'] ?? null),
                    'image' => $this->images[(string) $optionId][0]['thumb'] ?? null,
                    'sort' => $options[$optionId]['sort_order'] ?? 0,
                ])
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->map(fn (array $option): array => array_diff_key($option, ['sort' => true]))
                ->values()
                ->all();

            $code = $attributes[$attributeId]['code'] ?? '';
            $isSwatch = collect($swatchCodes)->contains(fn (string $swatchCode): bool => preg_match('/^'.preg_quote($swatchCode, '/').'(?:$|[-_])/i', $code) === 1)
                || collect($axisOptions)->contains(fn (array $option): bool => $option['hex'] !== null || $option['image'] !== null);

            $axes[] = [
                'attributeId' => $attributeId,
                'code' => $code,
                'name' => $attributes[$attributeId]['name'] ?? $code,
                'type' => $isSwatch ? self::AXIS_SWATCH : self::AXIS_BUTTON,
                'options' => $axisOptions,
            ];
        }

        usort($axes, fn (array $a, array $b): int => [$a['type'] === self::AXIS_SWATCH ? 0 : 1, $a['attributeId']] <=> [$b['type'] === self::AXIS_SWATCH ? 0 : 1, $b['attributeId']]);

        return $axes;
    }

    /**
     * @param  EloquentCollection<int, ProductAttributeValue>  $rows
     * @param  array<int, array{code: string, input_type: string, name: string}>  $attributes
     * @param  array<int, array{attribute_id: int, code: string|null, name: string, sort_order: int}>  $options
     * @return list<array{name: string, value: string}>
     */
    protected function buildSpecs(EloquentCollection $rows, array $attributes, array $options): array
    {
        $specs = [];
        foreach ($rows->groupBy('attribute_id') as $attributeId => $values) {
            $attribute = $attributes[(int) $attributeId] ?? null;

            if ($attribute === null) {
                continue;
            }

            $parts = $values->map(function (ProductAttributeValue $value) use ($attribute, $options): ?string {
                if ($value->attribute_option_id !== null) {
                    return $options[$value->attribute_option_id]['name'] ?? null;
                }

                $text = trim((string) $value->value_text);

                if ($attribute['input_type'] === 'boolean' && in_array($text, ['1', '2'], true)) {
                    return trans($text === '1' ? 'storefront::product.details.yes' : 'storefront::product.details.no');
                }

                return $text !== '' ? $text : null;
            })->filter()->values()->all();

            if ($parts !== []) {
                $specs[] = ['name' => $attribute['name'], 'value' => implode(', ', $parts)];
            }
        }

        return $specs;
    }

    protected function loadCategory(): void
    {
        $this->category = CatalogCategory::query()->find($this->product->catalog_category_id, ['id', 'parent_id', 'path', 'depth', 'slug', 'status', 'is_leaf', ...CatalogCategory::INHERITABLE_FIELDS]);
        $this->categoryDefaults = $this->category !== null ? $this->defaults->for($this->category) : $this->defaults->inheritedFrom(null);

        if ($this->category === null) {
            return;
        }

        $pathIds = $this->category->pathIds() ?: [$this->category->id];
        [$locale, $fallback] = $this->locales();

        $rows = DB::table('catalog_category as c')
            ->leftJoin('catalog_category_translation as t', fn ($join) => $join->on('t.catalog_category_id', '=', 'c.id')->where('t.locale', $locale))
            ->leftJoin('catalog_category_translation as f', fn ($join) => $join->on('f.catalog_category_id', '=', 'c.id')->where('f.locale', $fallback))
            ->whereIn('c.id', $pathIds)
            ->whereNull('c.deleted_at')
            ->where('c.status', config('core.enabled'))
            ->get(['c.id', 'c.slug', 't.name as name', 'f.name as fallback_name'])
            ->keyBy('id');

        foreach ($pathIds as $id) {
            $row = $rows->get($id);

            if ($row !== null) {
                $this->categoryTrail[] = [
                    'id' => (int) $row->id,
                    'name' => (string) ($row->name ?? $row->fallback_name ?? $row->slug),
                    'slug' => (string) $row->slug,
                    'url' => StorefrontUrls::category((string) $row->slug),
                ];
            }
        }
    }

    protected function loadBrand(): void
    {
        if ($this->product->catalog_brand_id === null) {
            return;
        }

        [$locale, $fallback] = $this->locales();

        $row = DB::table('catalog_brand as b')
            ->leftJoin('catalog_brand_translation as t', fn ($join) => $join->on('t.catalog_brand_id', '=', 'b.id')->where('t.locale', $locale))
            ->leftJoin('catalog_brand_translation as f', fn ($join) => $join->on('f.catalog_brand_id', '=', 'b.id')->where('f.locale', $fallback))
            ->where('b.id', $this->product->catalog_brand_id)
            ->where('b.status', config('core.enabled'))
            ->first(['b.id', 'b.slug', 'b.is_verified', 'b.size_chart_id', 't.name as name', 'f.name as fallback_name']);

        if ($row !== null) {
            $this->brand = [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? $row->fallback_name ?? $row->slug),
                'slug' => (string) $row->slug,
                'verified' => (int) $row->is_verified === (int) config('core.yes'),
                'size_chart_id' => $row->size_chart_id !== null ? (int) $row->size_chart_id : null,
            ];
        }
    }

    /**
     * The brand's size chart, else the category's (inherited), when enabled.
     */
    protected function loadSizeChart(): ?CatalogSizeChart
    {
        $chartId = $this->brand['size_chart_id'] ?? null;
        $chartId ??= $this->categoryDefaults['size_chart_id'] ?? null;

        if ($chartId === null) {
            return null;
        }

        return CatalogSizeChart::query()->where('status', config('core.enabled'))->find($chartId);
    }

    /**
     * @param  list<int|string|null>  $attributeIds
     * @return array<int, array{code: string, input_type: string, name: string}>
     */
    protected function attributeNames(array $attributeIds): array
    {
        $attributeIds = array_values(array_unique(array_filter(array_map('intval', $attributeIds))));

        if ($attributeIds === []) {
            return [];
        }

        [$locale, $fallback] = $this->locales();

        return DB::table('attribute as a')
            ->leftJoin('attribute_translation as t', fn ($join) => $join->on('t.attribute_id', '=', 'a.id')->where('t.locale', $locale))
            ->leftJoin('attribute_translation as f', fn ($join) => $join->on('f.attribute_id', '=', 'a.id')->where('f.locale', $fallback))
            ->whereIn('a.id', $attributeIds)
            ->get(['a.id', 'a.code', 'a.input_type', 't.name as name', 'f.name as fallback_name'])
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => [
                'code' => (string) $row->code,
                'input_type' => (string) $row->input_type,
                'name' => (string) ($row->name ?? $row->fallback_name ?? Str::headline((string) $row->code)),
            ]])
            ->all();
    }

    /**
     * @param  list<int|string|null>  $optionIds
     * @return array<int, array{attribute_id: int, code: string|null, name: string, sort_order: int}>
     */
    protected function optionNames(array $optionIds): array
    {
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds))));

        if ($optionIds === []) {
            return [];
        }

        [$locale, $fallback] = $this->locales();

        return DB::table('attribute_option as o')
            ->leftJoin('attribute_option_translation as t', fn ($join) => $join->on('t.attribute_option_id', '=', 'o.id')->where('t.locale', $locale))
            ->leftJoin('attribute_option_translation as f', fn ($join) => $join->on('f.attribute_option_id', '=', 'o.id')->where('f.locale', $fallback))
            ->whereIn('o.id', $optionIds)
            ->get(['o.id', 'o.attribute_id', 'o.custom_option', 'o.sort_order', 't.name as name', 'f.name as fallback_name'])
            ->mapWithKeys(fn (object $row): array => [(int) $row->id => [
                'attribute_id' => (int) $row->attribute_id,
                'code' => $row->custom_option !== null ? (string) $row->custom_option : null,
                'name' => (string) ($row->name ?? $row->fallback_name ?? $row->custom_option ?? $row->id),
                'sort_order' => (int) $row->sort_order,
            ]])
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function locales(): array
    {
        return [app()->getLocale(), (string) config('translatable.fallback_locale', config('app.fallback_locale', 'en'))];
    }

    protected static function hex(?string $code): ?string
    {
        return is_string($code) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $code) ? $code : null;
    }
}
