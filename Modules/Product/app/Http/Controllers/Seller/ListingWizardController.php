<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Catalog\Services\CategoryAttributeResolver;
use Modules\Catalog\Services\CategoryDefaults;
use Modules\Catalog\Support\ResolvedAttribute;
use Modules\Core\Support\Money;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Http\Requests\Seller\PricingStepRequest;
use Modules\Product\Http\Requests\Seller\StoreListingStepRequest;
use Modules\Product\Http\Requests\Seller\SubmitListingRequest;
use Modules\Product\Http\Requests\Seller\VariantMatrixRequest;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\BrandRequestService;
use Modules\Product\Services\EarningsCalculator;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductModerationService;
use Modules\Product\Support\Listing\CategoryPicker;
use Modules\Product\Support\Listing\ListingDraft;
use Modules\Product\Support\Listing\ListingWizard;
use Modules\Product\Support\OptionLabels;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Single listing wizard (FR-SCT-001/002): category → details → variants → images → pricing →
 * review. The draft is created when the details step is first saved and saved again after
 * every step, always through ProductFactoryService (published products: QC-sensitive edits
 * become pending changes).
 */
class ListingWizardController extends SellerPanelController
{
    public function __construct(
        private readonly ProductFactoryService $factory,
        private readonly ProductModerationService $moderation,
        private readonly ListingDraft $drafts,
        private readonly CategoryPicker $categories,
        private readonly CategoryAttributeResolver $attributes,
        private readonly CategoryDefaults $defaults,
        private readonly BrandRequestService $brands,
        private readonly EarningsCalculator $calculator,
        private readonly OptionLabels $labels,
    ) {}

    /**
     * Step 1: pick a leaf category (type-ahead + browse tree). With ?product= it moves a
     * never-published draft to another category.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $product = null;

        if ($request->filled('product')) {
            $product = $this->ownedProduct((int) $request->query('product'));

            if (! ListingWizard::categoryChangeable($product)) {
                return redirect()->to($this->stepUrl($product, ListingWizard::STEP_DETAILS))->with('error', trans('product::seller.wizard.category.locked'));
            }
        }

        return view('product::seller.product.wizard.category', [
            'sellerStoreName' => $this->seller()->displayName(),
            'product' => $product,
            'step' => ListingWizard::STEP_CATEGORY,
            'tree' => $this->categories->tree(),
            'selectedId' => $product?->catalog_category_id,
            'selectedLabel' => $product !== null ? $this->categories->breadcrumb($product->catalog_category_id) : null,
        ]);
    }

    /**
     * Step 1 submitted: continue to the details of a new listing, or move the draft.
     */
    public function chooseCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'catalog_category_id' => ['required', 'integer'],
            'product' => ['nullable', 'integer'],
        ]);
        $categoryId = (int) $validated['catalog_category_id'];

        if (! $this->categories->isListable($categoryId)) {
            return back()->withInput()->withErrors(['catalog_category_id' => trans('product::seller.wizard.category.invalid')]);
        }

        if (empty($validated['product'])) {
            return redirect()->to(frontRoute('seller.listing.details', [['category' => $categoryId]]));
        }

        $product = $this->ownedProduct((int) $validated['product']);
        abort_unless(ListingWizard::categoryChangeable($product), 403);

        if ($product->catalog_category_id !== $categoryId) {
            try {
                $this->factory->update($product, $this->drafts->toData($this->moveToCategory($this->drafts->fromProduct($product), $categoryId)), $this->sellerUser());
            } catch (ProductValidationException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        return redirect()->to($this->stepUrl($product, ListingWizard::STEP_DETAILS))->with('success', trans('product::seller.wizard.messages.category_changed'));
    }

    /**
     * Step 2 of a new listing (nothing saved yet).
     */
    public function details(Request $request): View|RedirectResponse
    {
        $categoryId = (int) $request->query('category');

        if (! $this->categories->isListable($categoryId)) {
            return redirect()->to(frontRoute('seller.listing.create'))->with('error', trans('product::seller.wizard.category.invalid'));
        }

        $category = CatalogCategory::query()->findOrFail($categoryId);

        return view('product::seller.product.wizard.details', $this->detailsData(null, $this->drafts->blank($this->seller()->id, $category), $category));
    }

    /**
     * Step 2 of a new listing submitted: create the draft.
     */
    public function store(StoreListingStepRequest $request): RedirectResponse
    {
        $categoryId = (int) $request->input('catalog_category_id');

        if (! $this->categories->isListable($categoryId)) {
            return back()->withInput()->with('error', trans('product::seller.wizard.category.invalid'));
        }

        $category = CatalogCategory::query()->findOrFail($categoryId);

        try {
            $product = $this->factory->create(
                $this->drafts->toData($this->withDetails($this->drafts->blank($this->seller()->id, $category), $request)),
                $this->sellerUser(),
            );
        } catch (ProductValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->to($this->stepUrl($product, ListingWizard::STEP_VARIANTS))->with('success', trans('product::seller.wizard.messages.draft_saved'));
    }

    /**
     * Steps 2–6 of a saved product.
     */
    public function step(Request $request): View|RedirectResponse
    {
        $product = $this->ownedProduct((int) $request->route('product'));
        $step = (string) $request->route('step');

        if (! ListingWizard::editable($product)) {
            return redirect()->to(frontRoute('seller.product.index'))->with('error', trans('product::seller.wizard.messages.not_editable', ['status' => $product->status->label()]));
        }

        $draft = $this->drafts->fromProduct($product);
        $category = CatalogCategory::query()->findOrFail((int) $draft['catalog_category_id']);

        return match ($step) {
            ListingWizard::STEP_DETAILS => view('product::seller.product.wizard.details', $this->detailsData($product, $draft, $category)),
            ListingWizard::STEP_VARIANTS => view('product::seller.product.wizard.variants', $this->variantsData($product, $draft, $category)),
            ListingWizard::STEP_IMAGES => view('product::seller.product.wizard.images', $this->imagesData($product, $draft, $category)),
            ListingWizard::STEP_PRICING => view('product::seller.product.wizard.pricing', $this->pricingData($product, $draft, $category)),
            default => view('product::seller.product.wizard.review', $this->reviewData($product, $draft, $category)),
        };
    }

    /**
     * Save the details or pricing step.
     */
    public function save(Request $request): RedirectResponse
    {
        $step = (string) $request->route('step');
        $form = $step === ListingWizard::STEP_PRICING ? app(PricingStepRequest::class) : app(StoreListingStepRequest::class);
        $product = $form->product();

        if (! ListingWizard::editable($product)) {
            return redirect()->to(frontRoute('seller.product.index'))->with('error', trans('product::seller.wizard.messages.not_editable', ['status' => $product->status->label()]));
        }

        $draft = $this->drafts->fromProduct($product);
        $draft = $step === ListingWizard::STEP_PRICING ? $this->withPricing($draft, $form) : $this->withDetails($draft, $form);

        try {
            $product = $this->factory->update($product, $this->drafts->toData($draft), $this->sellerUser());
        } catch (ProductValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->to($this->stepUrl($product, (string) ListingWizard::next($step)))->with('success', $this->savedMessage($product));
    }

    /**
     * Step 3: the variant matrix (JSON from the Alpine component).
     */
    public function saveVariants(VariantMatrixRequest $request): JsonResponse
    {
        $product = $request->product();
        abort_unless(ListingWizard::editable($product), 403, trans('product::seller.wizard.messages.not_editable', ['status' => $product->status->label()]));

        $draft = $this->drafts->fromProduct($product);
        $existing = collect((array) $draft['variants'])->filter(fn (array $variant): bool => ($variant['id'] ?? null) !== null)->keyBy('id');

        $draft['variants'] = array_map(function (array $row, int $position) use ($existing): array {
            $fields = [
                'seller_sku' => trim((string) $row['seller_sku']),
                'mrp' => (string) $row['mrp'],
                'price' => (string) $row['price'],
                'stock' => (int) $row['stock'],
                'weight_grams' => (int) $row['weight_grams'],
                'options' => array_map('intval', (array) ($row['options'] ?? [])),
                'position' => $position,
            ];
            $id = isset($row['id']) ? (int) $row['id'] : null;

            return $id !== null ? array_merge((array) $existing->get($id, []), $fields, ['id' => $id]) : $fields + ['id' => null];
        }, array_values((array) $request->input('variants')), array_keys(array_values((array) $request->input('variants'))));

        try {
            $product = $this->factory->update($product, $this->drafts->toData($draft), $this->sellerUser());
        } catch (ProductValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => array_map(fn (string $message): array => [$message], $e->errors())], 422);
        }

        return response()->json([
            'message' => $this->savedMessage($product),
            'redirect' => $this->stepUrl($product, ListingWizard::STEP_IMAGES),
        ]);
    }

    /**
     * Step 6: send to QC.
     */
    public function submit(SubmitListingRequest $request): RedirectResponse
    {
        $product = $request->product();

        if (! ListingWizard::submittable($product)) {
            return redirect()->to($this->stepUrl($product, ListingWizard::STEP_REVIEW))->with('error', trans('product::seller.wizard.messages.not_submittable'));
        }

        try {
            $product = $this->moderation->submit($product, $this->sellerUser());
        } catch (ProductException $e) {
            return redirect()->to($this->stepUrl($product, ListingWizard::STEP_REVIEW))->with('error', $e->getMessage());
        }

        return redirect()->to(frontRoute('seller.product.index', [['tab' => $product->status->value]]))
            ->with('success', trans('product::seller.wizard.messages.submitted'));
    }

    /**
     * Type-ahead over listable leaf categories.
     */
    public function categorySearch(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->categories->search((string) $request->query('q', ''))]);
    }

    /**
     * Is a seller SKU free? (?sku=, ?product= to ignore the product's own variants)
     */
    public function skuCheck(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));

        if ($sku === '') {
            return response()->json(['available' => false, 'message' => trans('product::product.validation.seller_sku')]);
        }

        $taken = ProductVariant::query()
            ->where('seller_id', $this->seller()->id)
            ->where('seller_sku', $sku)
            ->when($request->filled('product'), fn ($query) => $query->where('product_id', '!=', (int) $request->query('product')))
            ->exists();

        return response()->json([
            'available' => ! $taken,
            'message' => $taken ? trans('product::product.validation.seller_sku_taken') : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function detailsData(?Product $product, array $draft, CatalogCategory $category): array
    {
        $seller = $this->seller();
        $defaults = $this->defaults->for($category);

        return $this->common($product, ListingWizard::STEP_DETAILS, $category) + [
            'draft' => $draft,
            'locale' => $this->factory->defaultLocale(),
            'specs' => $this->attributes->for($category)->reject(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->values(),
            'brandOptions' => $this->brands->options($seller),
            'canEditTax' => $seller->isGstRegistered(),
            'gstRates' => array_map('strval', (array) config('product.gst_rates')),
            'defaultGst' => $defaults['gst_percent'],
            'defaultHsn' => $defaults['hsn_code'],
            'maxHighlights' => (int) config('product.max_highlights', 8),
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function variantsData(Product $product, array $draft, CatalogCategory $category): array
    {
        $rows = $this->drafts->variantRows($draft, $product->isPublished());
        $axes = $this->attributes->for($category)
            ->filter(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)
            ->map(fn (ResolvedAttribute $attribute): array => [
                'id' => $attribute->attributeId,
                'name' => $attribute->name,
                'options' => array_map(fn (array $option): array => ['id' => (int) $option['id'], 'name' => (string) $option['name']], $attribute->options),
            ])
            ->values()
            ->all();

        return $this->common($product, ListingWizard::STEP_VARIANTS, $category) + [
            'matrix' => [
                'axes' => $axes,
                'rows' => array_map(fn (array $row): array => array_intersect_key($row, array_flip(['id', 'key', 'options', 'seller_sku', 'mrp', 'price', 'stock', 'weight_grams', 'locked'])), $rows),
                'maxVariants' => (int) config('product.max_variants', 100),
                'published' => $product->isPublished(),
                'productId' => $product->id,
                'saveUrl' => frontRoute('seller.listing.variants.save', [$product->id]),
                'skuCheckUrl' => frontRoute('seller.listing.sku_check'),
                'baseSku' => Str::upper(Str::limit((string) preg_replace('/[^A-Za-z0-9]+/', '', (string) ($draft['translations'][$this->factory->defaultLocale()]['name'] ?? 'SKU')), 8, '')),
                'labels' => [
                    'removed' => trans($product->isPublished() ? 'product::seller.wizard.variants.will_disable' : 'product::seller.wizard.variants.will_delete'),
                    'skuTaken' => trans('product::product.validation.seller_sku_taken'),
                    'priceAboveMrp' => trans('product::seller.wizard.variants.price_above_mrp'),
                    'required' => trans('product::seller.wizard.variants.required'),
                    'tooMany' => trans('product::seller.wizard.variants.too_many', ['max' => config('product.max_variants', 100)]),
                    'duplicateSku' => trans('product::product.validation.seller_sku_duplicate'),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function imagesData(Product $product, array $draft, CatalogCategory $category): array
    {
        $colourAxis = $this->colourAxis($category);
        $colourOptions = [];

        if ($colourAxis !== null) {
            $used = collect((array) $draft['variants'])->map(fn (array $variant): ?int => isset($variant['options'][$colourAxis->attributeId]) ? (int) $variant['options'][$colourAxis->attributeId] : null)->filter()->unique()->values()->all();
            $colourOptions = $this->labels->for($used);
        }

        return $this->common($product, ListingWizard::STEP_IMAGES, $category) + [
            'uploader' => [
                'images' => self::imagePayload((array) $draft['images']),
                'uploadUrl' => frontRoute('seller.image.upload', [$product->id]),
                'deleteUrl' => frontRoute('seller.image.delete', [$product->id]),
                'reorderUrl' => frontRoute('seller.image.reorder', [$product->id]),
                'maxImages' => (int) config('product.max_images', 8),
                'maxPerOption' => (int) config('product.max_images_per_option', 8),
                'minPx' => (int) config('product.min_image_px', 500),
                'maxKb' => (int) config('product.upload.max_size_kb', 5120),
                'types' => explode(',', (string) config('product.upload.image_types')),
                'groups' => array_merge(
                    [['id' => null, 'name' => trans('product::seller.wizard.images.shared')]],
                    array_map(fn (int $id, string $name): array => ['id' => $id, 'name' => $name], array_keys($colourOptions), array_values($colourOptions)),
                ),
                'labels' => [
                    'tooSmall' => trans('product::product.validation.image_pixels', ['px' => config('product.min_image_px', 500)]),
                    'tooBig' => trans('product::product.validation.image_size', ['kb' => config('product.upload.max_size_kb')]),
                    'wrongType' => trans('product::product.validation.image_type', ['types' => config('product.upload.image_types')]),
                    'full' => trans('product::product.validation.image_count', ['max' => config('product.max_images', 8)]),
                    'deleteConfirm' => trans('product::seller.wizard.images.delete_confirm'),
                ],
            ],
            'colourAxisName' => $colourAxis?->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function pricingData(Product $product, array $draft, CatalogCategory $category): array
    {
        $rows = $this->drafts->variantRows($draft, $product->isPublished());
        $labels = $this->labels->for(array_merge(...array_map(fn (array $row): array => array_values($row['options']), $rows ?: [['options' => []]])));
        $seller = $this->seller();
        $gst = (string) $draft['gst_percent'] ?: null;

        foreach ($rows as &$row) {
            $row['label'] = OptionLabels::combination($row['options'], $labels) ?: trans('product::seller.wizard.variants.single');
            $row['earnings'] = preg_match('/^\d+(\.\d{1,2})?$/', (string) $row['price']) && Money::of((string) $row['price'])->isPositive()
                ? EarningsCalculator::toArray($this->calculator->estimate(Money::of((string) $row['price']), $category, $seller, max(1, (int) $row['weight_grams']), $gst))
                : null;
        }
        unset($row);

        return $this->common($product, ListingWizard::STEP_PRICING, $category) + [
            'rows' => $rows,
            'calculatorUrl' => frontRoute('seller.calculator.calculate'),
            'gstPercent' => $gst,
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function reviewData(Product $product, array $draft, CatalogCategory $category): array
    {
        $rows = $this->drafts->variantRows($draft, $product->isPublished());
        $optionIds = array_merge(...array_map(fn (array $row): array => array_values($row['options']), $rows ?: [['options' => []]]));
        $specs = $this->attributes->for($category)->reject(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->keyBy('attributeId');

        foreach ((array) $draft['attributes'] as $value) {
            foreach ((array) $value as $optionId) {
                if (is_int($optionId)) {
                    $optionIds[] = $optionId;
                }
            }
        }

        $labels = $this->labels->for($optionIds);
        $specLines = [];
        foreach ((array) $draft['attributes'] as $attributeId => $value) {
            $attribute = $specs->get($attributeId);
            if ($attribute === null) {
                continue;
            }

            $specLines[$attribute->name] = match (true) {
                $attribute->inputType === 'boolean' => (int) $value === (int) config('core.yes') ? trans('product::seller.template.yes') : trans('product::seller.template.no'),
                is_array($value) => implode(', ', array_map(fn ($id): string => $labels[(int) $id] ?? (string) $id, $value)),
                default => (string) $value,
            };
        }

        foreach ($rows as &$row) {
            $row['label'] = OptionLabels::combination($row['options'], $labels) ?: trans('product::seller.wizard.variants.single');
        }
        unset($row);

        $locale = $this->factory->defaultLocale();

        return $this->common($product, ListingWizard::STEP_REVIEW, $category) + [
            'draft' => $draft,
            'translation' => (array) ($draft['translations'][$locale] ?? []),
            'rows' => $rows,
            'specLines' => $specLines,
            'images' => self::imagePayload((array) $draft['images']),
            'brandName' => isset($draft['catalog_brand_id']) ? ($this->brands->options($this->seller())[(int) $draft['catalog_brand_id']] ?? null) : null,
            'missing' => ListingWizard::submittable($product) ? $this->moderation->missingForSubmission($product) : [],
            'submittable' => ListingWizard::submittable($product),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function common(?Product $product, string $step, CatalogCategory $category): array
    {
        return [
            'sellerStoreName' => $this->seller()->displayName(),
            'product' => $product,
            'step' => $step,
            'category' => $category,
            'categoryLabel' => $this->categories->breadcrumb($category->id),
            'categoryChangeable' => $product === null || ListingWizard::categoryChangeable($product),
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function withDetails(array $draft, StoreListingStepRequest $request): array
    {
        $locale = $this->factory->defaultLocale();
        $input = (array) $request->input("translations.{$locale}", []);

        $draft['translations'][$locale] = array_merge((array) ($draft['translations'][$locale] ?? []), [
            'name' => (string) ($input['name'] ?? ''),
            'description' => $input['description'] ?? null,
            'highlights' => array_values(array_filter(array_map(fn ($line): string => trim((string) $line), (array) ($input['highlights'] ?? [])), fn (string $line): bool => $line !== '')),
        ]);

        $brandRequest = trim((string) $request->input('brand_request', ''));
        $draft['catalog_brand_id'] = $brandRequest !== ''
            ? $this->brands->request($this->seller(), $brandRequest)->id
            : ($request->filled('catalog_brand_id') ? (int) $request->input('catalog_brand_id') : null);

        $specs = $this->attributes->for((int) $draft['catalog_category_id'])->reject(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->keyBy('attributeId');
        $attributes = [];
        foreach ((array) $request->input('attributes', []) as $attributeId => $value) {
            $attribute = $specs->get((int) $attributeId);

            if ($attribute === null || $value === null || $value === '' || $value === []) {
                continue;
            }

            $attributes[(int) $attributeId] = match (true) {
                in_array($attribute->inputType, ProductFactoryService::TEXT_INPUT_TYPES, true), $attribute->inputType === 'boolean' => is_scalar($value) ? (string) $value : '',
                default => array_values(array_map('intval', array_filter((array) $value, fn ($id): bool => $id !== null && $id !== ''))),
            };
        }
        $draft['attributes'] = $attributes;

        if ($this->seller()->isGstRegistered()) {
            $draft['hsn_code'] = $request->filled('hsn_code') ? (string) $request->input('hsn_code') : null;
            $draft['gst_percent'] = $request->filled('gst_percent') ? (string) $request->input('gst_percent') : null;
        }

        $party = fn (string $key): ?array => array_filter((array) $request->input($key, []), fn ($value): bool => $value !== null && trim((string) $value) !== '') === []
            ? null
            : ['name' => trim((string) $request->input($key.'.name', '')), 'address' => trim((string) $request->input($key.'.address', ''))];

        $draft['country_of_origin'] = (string) $request->input('country_of_origin', 'IN');
        $draft['manufacturer_details'] = $party('manufacturer_details');
        $draft['packer_details'] = $party('packer_details');
        $draft['importer_details'] = $party('importer_details');
        $draft['search_keywords'] = $request->input('search_keywords');

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function withPricing(array $draft, PricingStepRequest $request): array
    {
        $input = array_values((array) $request->input('variants', []));
        $variants = array_values((array) $draft['variants']);

        foreach ($variants as $index => &$variant) {
            if (! isset($input[$index])) {
                continue;
            }

            $row = $input[$index];
            $variant = array_merge($variant, [
                'seller_sku' => trim((string) $row['seller_sku']),
                'mrp' => (string) $row['mrp'],
                'price' => (string) $row['price'],
                'stock' => (int) $row['stock'],
                'weight_grams' => (int) $row['weight_grams'],
                'length_cm' => ($row['length_cm'] ?? '') !== '' ? (string) $row['length_cm'] : null,
                'breadth_cm' => ($row['breadth_cm'] ?? '') !== '' ? (string) $row['breadth_cm'] : null,
                'height_cm' => ($row['height_cm'] ?? '') !== '' ? (string) $row['height_cm'] : null,
                'low_stock_threshold' => ($row['low_stock_threshold'] ?? '') !== '' ? (int) $row['low_stock_threshold'] : null,
                'status' => (int) ($row['status'] ?? config('core.enabled')),
            ]);
        }
        unset($variant);

        $draft['variants'] = $variants;

        return $draft;
    }

    /**
     * A never-published draft moved to another leaf: specifications of the old category go,
     * and so do variants and colour images when the variant axes differ.
     *
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    private function moveToCategory(array $draft, int $categoryId): array
    {
        $oldAxes = $this->attributes->variantAxes((int) $draft['catalog_category_id'])->pluck('attributeId')->sort()->values()->all();
        $newAttributes = $this->attributes->for($categoryId);
        $newAxes = $newAttributes->filter(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->pluck('attributeId')->sort()->values()->all();
        $newSpecs = $newAttributes->reject(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis)->pluck('attributeId')->all();

        $draft['catalog_category_id'] = $categoryId;
        $draft['attributes'] = array_intersect_key((array) $draft['attributes'], array_flip($newSpecs));
        $draft['hsn_code'] = null;
        $draft['gst_percent'] = null;

        if ($oldAxes !== $newAxes) {
            $draft['variants'] = [];
            $draft['images'] = array_values(array_filter((array) $draft['images'], fn (array $image): bool => ($image['attribute_option_id'] ?? null) === null));
        }

        return $draft;
    }

    /**
     * The variant axis colour images belong to: one whose code mentions colour.
     */
    private function colourAxis(CatalogCategory $category): ?ResolvedAttribute
    {
        return $this->attributes->for($category)
            ->first(fn (ResolvedAttribute $attribute): bool => $attribute->isVariantAxis && preg_match('/colou?r/i', $attribute->code.' '.$attribute->name) === 1);
    }

    private function ownedProduct(int $id): Product
    {
        $product = Product::query()->findOrFail($id);
        $this->authorizeOwned($product);

        return $product;
    }

    private function stepUrl(Product $product, string $step): string
    {
        return frontRoute('seller.listing.step', [$product->id, $step]);
    }

    private function savedMessage(Product $product): string
    {
        return $product->hasPendingChanges()
            ? trans('product::seller.wizard.messages.changes_pending')
            : trans('product::seller.wizard.messages.draft_saved');
    }

    /**
     * Images for the uploader / review screens.
     *
     * @param  list<array<string, mixed>>  $images
     * @return list<array{path: string, url: string|null, attribute_option_id: int|null, width: int|null, height: int|null}>
     */
    public static function imagePayload(array $images): array
    {
        return array_values(array_map(fn (array $image): array => [
            'path' => (string) $image['path'],
            'url' => getImageUrl(['module' => (string) config('product.upload.folder'), 'image' => (string) $image['path'], 'defualt-image' => true]),
            'attribute_option_id' => isset($image['attribute_option_id']) ? (int) $image['attribute_option_id'] : null,
            'width' => isset($image['width']) ? (int) $image['width'] : null,
            'height' => isset($image['height']) ? (int) $image['height'] : null,
        ], $images));
    }
}
