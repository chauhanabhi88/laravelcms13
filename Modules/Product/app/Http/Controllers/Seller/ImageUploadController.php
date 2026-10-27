<?php

declare(strict_types=1);

namespace Modules\Product\Http\Controllers\Seller;

use Illuminate\Http\JsonResponse;
use Modules\Product\Exceptions\ProductValidationException;
use Modules\Product\Http\Requests\Seller\ImageChangeRequest;
use Modules\Product\Http\Requests\Seller\ImageUploadRequest;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductFactoryService;
use Modules\Product\Services\ProductImageService;
use Modules\Product\Support\Listing\ListingDraft;
use Modules\Product\Support\Listing\ListingWizard;
use Modules\Seller\Http\Controllers\Seller\SellerPanelController;

/**
 * Wizard step 4 (FR-PRD-003): upload, delete and reorder a product's images, the shared set
 * (1–8) and per-colour sets. Every change saves the product through ProductFactoryService;
 * on a published product the new image list waits in QC as a pending change.
 */
class ImageUploadController extends SellerPanelController
{
    public function __construct(
        private readonly ProductFactoryService $factory,
        private readonly ProductImageService $images,
        private readonly ListingDraft $drafts,
    ) {}

    public function store(ImageUploadRequest $request): JsonResponse
    {
        $product = $this->editable($request->product());
        $draft = $this->drafts->fromProduct($product);
        $optionId = $request->filled('attribute_option_id') ? (int) $request->input('attribute_option_id') : null;

        $inGroup = count(array_filter((array) $draft['images'], fn (array $image): bool => ($image['attribute_option_id'] ?? null) === $optionId));
        $max = (int) config($optionId === null ? 'product.max_images' : 'product.max_images_per_option', 8);

        if ($inGroup >= $max) {
            return $this->invalid(['image' => trans($optionId === null ? 'product::product.validation.image_count' : 'product::product.validation.image_option_count', ['max' => $max])]);
        }

        try {
            $image = $this->images->store($request->file('image'), $optionId, null, 'image');
        } catch (ProductValidationException $e) {
            return $this->invalid($e->errors());
        }

        $draft['images'][] = $image->toArray();

        return $this->save($product, $draft, [$image->path]);
    }

    public function destroy(ImageChangeRequest $request): JsonResponse
    {
        $product = $this->editable($request->product());
        $draft = $this->drafts->fromProduct($product);
        $path = (string) $request->input('path');

        $draft['images'] = array_values(array_filter((array) $draft['images'], fn (array $image): bool => $image['path'] !== $path));

        return $this->save($product, $draft);
    }

    /**
     * "paths": every image path in the new order (groups may be listed in any order; the
     * first shared image becomes the primary one).
     */
    public function reorder(ImageChangeRequest $request): JsonResponse
    {
        $product = $this->editable($request->product());
        $draft = $this->drafts->fromProduct($product);
        $byPath = collect((array) $draft['images'])->keyBy('path');
        $paths = array_values(array_unique(array_map('strval', (array) $request->input('paths', []))));

        if (count($paths) !== $byPath->count() || array_diff($paths, $byPath->keys()->all()) !== []) {
            return $this->invalid(['paths' => trans('product::seller.wizard.images.reorder_mismatch')]);
        }

        $draft['images'] = array_map(fn (string $path): array => $byPath->get($path), $paths);

        return $this->save($product, $draft);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  list<string>  $newPaths  files stored for this request, removed again on failure
     */
    private function save(Product $product, array $draft, array $newPaths = []): JsonResponse
    {
        try {
            $product = $this->factory->update($product, $this->drafts->toData($draft), $this->sellerUser());
        } catch (ProductValidationException $e) {
            $this->images->deleteUnreferenced($newPaths);

            return $this->invalid($e->errors());
        }

        return response()->json([
            'message' => $product->hasPendingChanges()
                ? trans('product::seller.wizard.messages.changes_pending')
                : trans('product::seller.wizard.images.saved'),
            'images' => ListingWizardController::imagePayload((array) $this->drafts->fromProduct($product)['images']),
        ]);
    }

    private function editable(Product $product): Product
    {
        abort_unless(ListingWizard::editable($product), 403, trans('product::seller.wizard.messages.not_editable', ['status' => $product->status->label()]));

        return $product;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function invalid(array $errors): JsonResponse
    {
        return response()->json([
            'message' => (string) reset($errors),
            'errors' => array_map(fn (string $message): array => [$message], $errors),
        ], 422);
    }
}
