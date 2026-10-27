<?php

declare(strict_types=1);

namespace Modules\Product\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Product\Exceptions\ProductException;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Imports\CatalogUploadImport;
use Modules\Product\Models\CatalogUpload;
use Modules\Product\Models\CatalogUploadRowError;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Support\Import\CatalogTemplate;
use Modules\Product\Support\Import\ImageFetcher;
use Modules\Product\Support\Import\MappedGroup;
use Modules\Product\Support\Import\RowToProductMapper;
use Modules\Product\Support\Listing\CategoryPicker;
use Modules\Product\Support\Listing\ListingDraft;
use Modules\Product\Support\Listing\ListingWizard;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;

/**
 * Processes a new-catalog upload (FR-SCT-003): reads the rows in chunks, groups them into
 * products, fetches the image URLs and writes each product through ProductFactoryService in
 * its own transaction, so one bad group never rolls back the rest. New products go to QC
 * unless the seller chose "save as drafts".
 *
 * Re-uploading Seller SKUs that already exist updates that product in place: drafts and
 * rejected products directly, live / paused products as pending changes (M04). The group then
 * is the whole product: variants left out are removed (or disabled once published).
 */
class CatalogImportService
{
    public function __construct(
        protected CatalogUploadService $uploads,
        protected RowToProductMapper $mapper,
        protected ImageFetcher $images,
        protected ProductImageService $imageFiles,
        protected ProductFactoryService $factory,
        protected ProductModerationService $moderation,
        protected ListingDraft $drafts,
        protected CategoryPicker $categories,
    ) {}

    public function process(CatalogUpload $upload): void
    {
        if (! $this->uploads->start($upload)) {
            return;
        }

        try {
            $this->run($upload);
        } catch (\Throwable $e) {
            Log::error('Catalog upload failed', ['upload' => $upload->id, 'exception' => $e]);
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.processing'));
        }
    }

    protected function run(CatalogUpload $upload): void
    {
        $seller = Seller::query()->findOrFail($upload->seller_id);
        $actor = $upload->seller_user_id !== null ? SellerUser::query()->find($upload->seller_user_id) : null;
        $category = $upload->catalog_category_id !== null ? CatalogCategory::query()->find($upload->catalog_category_id) : null;

        if (! $seller->isActive()) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.seller_inactive'));

            return;
        }

        if ($category === null || ! $this->categories->isListable($category->id)) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.category'));

            return;
        }

        $template = CatalogTemplate::for($category);
        $reader = new CatalogUploadImport((int) config('product.bulk_max_rows', 5000));

        try {
            Excel::import($reader, $upload->file_path, $this->uploads->disk());
        } catch (\Throwable $e) {
            Log::warning('Catalog upload unreadable', ['upload' => $upload->id, 'error' => $e->getMessage()]);
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.unreadable'));

            return;
        }

        if ($reader->tooManyRows()) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.too_many_rows', ['max' => config('product.bulk_max_rows')]), $reader->dataRowCount());

            return;
        }

        $map = $template->mapHeadings($reader->headings());
        $missing = $template->missingRequired($map);

        if ($missing !== []) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.missing_columns', ['columns' => implode(', ', $missing)]));

            return;
        }

        $groups = $this->mapper->map($template, $seller, $reader->keyedRows($map));
        $rawRows = $reader->rows();
        $problems = [];
        $failedRows = [];
        $total = $success = $failed = 0;

        foreach ($groups as $group) {
            $total += count($group->rows);

            if (! $group->hasErrors()) {
                $this->saveGroup($upload, $seller, $actor, $group);
            }

            foreach ($group->warnings as $row => $warnings) {
                foreach ($warnings as $warning) {
                    $problems[$row][] = $warning + ['level' => CatalogUploadRowError::LEVEL_WARNING];
                }
            }

            if ($group->hasErrors()) {
                $failed += count($group->rows);

                foreach ($group->errors as $row => $errors) {
                    foreach ($errors as $error) {
                        $problems[$row][] = $error + ['level' => CatalogUploadRowError::LEVEL_ERROR];
                    }
                }

                foreach ($group->rows as $row) {
                    $failedRows[$row] = $rawRows[$row] ?? [];
                }
            } else {
                $success += count($group->rows);
            }
        }

        if ($total === 0) {
            $this->uploads->fail($upload, trans('product::seller.uploads.file_errors.empty'));

            return;
        }

        $this->uploads->finish($upload, $total, $success, $failed, $problems, $reader->headings(), $failedRows);
    }

    /**
     * Create or update the group's product; problems are added to the group.
     */
    protected function saveGroup(CatalogUpload $upload, Seller $seller, ?SellerUser $actor, MappedGroup $group): void
    {
        $product = $this->existingProduct($seller, $group, $upload->catalog_category_id);

        if ($group->hasErrors()) {
            return;
        }

        $images = [];
        foreach ($group->imageUrls as $index => $url) {
            try {
                $images[] = $this->images->fetch($url)->toArray();
            } catch (ProductException $e) {
                $group->addError($group->firstRow(), 'image_url_'.($index + 1), $e->getMessage());
            }
        }

        if ($group->hasErrors()) {
            $this->imageFiles->deleteUnreferenced(array_column($images, 'path'));

            return;
        }

        try {
            $data = $product === null
                ? $this->drafts->toData($group->data + ['seller_id' => $seller->id, 'images' => $images])
                : $this->drafts->toData($this->mergeIntoDraft($this->drafts->fromProduct($product), $group, $images));

            $product = $product === null
                ? $this->factory->create($data, $actor)
                : $this->factory->update($product, $data, $actor);
        } catch (ProductValidationException $e) {
            foreach ($e->errors() as $key => $message) {
                $group->addError($group->rowForErrorKey((string) $key), MappedGroup::columnForErrorKey((string) $key), $message);
            }
            $this->imageFiles->deleteUnreferenced(array_column($images, 'path'));

            return;
        } catch (ProductException $e) {
            $group->addError($group->firstRow(), null, $e->getMessage());
            $this->imageFiles->deleteUnreferenced(array_column($images, 'path'));

            return;
        }

        if ($upload->savesAsDraft() || ! ListingWizard::submittable($product)) {
            return;
        }

        try {
            $this->moderation->submit($product, $actor);
        } catch (\Throwable $e) {
            $group->addError($group->firstRow(), null, trans('product::seller.uploads.row_errors.saved_as_draft', ['reason' => $e->getMessage()]));
        }
    }

    /**
     * The seller's product already holding the group's SKUs, or null for a new product.
     */
    protected function existingProduct(Seller $seller, MappedGroup $group, ?int $categoryId): ?Product
    {
        $skus = array_values(array_filter(array_map(fn (array $variant): string => (string) $variant['seller_sku'], $group->data['variants'] ?? [])));

        if ($skus === []) {
            return null;
        }

        $productIds = ProductVariant::query()
            ->where('seller_id', $seller->id)
            ->whereIn('seller_sku', $skus)
            ->distinct()
            ->pluck('product_id')
            ->all();

        if ($productIds === []) {
            return null;
        }

        if (count($productIds) > 1) {
            $group->addError($group->firstRow(), 'seller_sku', trans('product::seller.uploads.row_errors.sku_several_products'));

            return null;
        }

        $product = Product::query()->withTrashed()->find($productIds[0]);

        if ($product === null || $product->trashed()) {
            $group->addError($group->firstRow(), 'seller_sku', trans('product::seller.uploads.row_errors.sku_deleted_product'));

            return null;
        }

        if (! ListingWizard::editable($product)) {
            $group->addError($group->firstRow(), 'seller_sku', trans('product::seller.uploads.row_errors.product_locked', ['status' => $product->status->label()]));

            return null;
        }

        if ($product->catalog_category_id !== $categoryId) {
            $group->addError($group->firstRow(), 'group_id', trans('product::seller.uploads.row_errors.category_mismatch'));

            return null;
        }

        return $product;
    }

    /**
     * The existing product's draft with the group's content, images and variants (matched
     * by Seller SKU, so existing variants keep their ids).
     *
     * @param  array<string, mixed>  $draft
     * @param  list<array<string, mixed>>  $images
     * @return array<string, mixed>
     */
    protected function mergeIntoDraft(array $draft, MappedGroup $group, array $images): array
    {
        $data = $group->data;

        foreach ((array) $data['translations'] as $locale => $fields) {
            $draft['translations'][$locale] = array_merge((array) ($draft['translations'][$locale] ?? []), $fields);
        }

        foreach (['catalog_brand_id', 'hsn_code', 'gst_percent', 'manufacturer_details'] as $key) {
            if (array_key_exists($key, $data)) {
                $draft[$key] = $data[$key];
            }
        }

        $draft['country_of_origin'] = $data['country_of_origin'];
        $draft['attributes'] = $data['attributes'];
        $draft['images'] = $images;

        $existing = [];
        foreach ((array) $draft['variants'] as $variant) {
            if (($variant['id'] ?? null) !== null) {
                $existing[Str::lower((string) $variant['seller_sku'])] = $variant;
            }
        }

        $draft['variants'] = array_map(function (array $variant) use ($existing): array {
            $current = $existing[Str::lower((string) $variant['seller_sku'])] ?? null;

            return $current === null ? $variant : array_merge($current, $variant, ['id' => $current['id']]);
        }, (array) $data['variants']);

        return $draft;
    }
}
