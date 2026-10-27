<?php

declare(strict_types=1);

namespace Modules\Product\Support\Listing;

use Modules\Product\Enums\ProductStatus;
use Modules\Product\Models\Product;

/**
 * Steps of the single listing wizard (FR-SCT-001) and which products it may edit.
 *
 * Step 1 (category) has no product yet; the draft is created when the details step is first
 * saved, and every later step saves the draft again, so the seller can resume from the
 * product list.
 */
final class ListingWizard
{
    public const STEP_CATEGORY = 'category';

    public const STEP_DETAILS = 'details';

    public const STEP_VARIANTS = 'variants';

    public const STEP_IMAGES = 'images';

    public const STEP_PRICING = 'pricing';

    public const STEP_REVIEW = 'review';

    /**
     * Steps that take a product, in order.
     */
    public const PRODUCT_STEPS = [
        self::STEP_DETAILS,
        self::STEP_VARIANTS,
        self::STEP_IMAGES,
        self::STEP_PRICING,
        self::STEP_REVIEW,
    ];

    /**
     * @return list<string>
     */
    public static function steps(): array
    {
        return [self::STEP_CATEGORY, ...self::PRODUCT_STEPS];
    }

    /**
     * Step labels for <x-theme::stepper>.
     *
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(fn (string $step): string => trans('product::seller.wizard.steps.'.$step), self::steps());
    }

    /**
     * 1-based position of a step (for the stepper).
     */
    public static function position(string $step): int
    {
        return (int) array_search($step, self::steps(), true) + 1;
    }

    public static function next(string $step): ?string
    {
        $steps = self::steps();
        $index = array_search($step, $steps, true);

        return $index === false ? null : ($steps[$index + 1] ?? null);
    }

    public static function previous(string $step): ?string
    {
        $steps = self::steps();
        $index = array_search($step, $steps, true);

        return $index === false || $index === 0 ? null : $steps[$index - 1];
    }

    /**
     * Products the seller may edit in the wizard. Products in QC or blocked are read-only;
     * edits to live / paused products go to QC as pending changes (M04).
     */
    public static function editable(Product $product): bool
    {
        return in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected, ProductStatus::Live, ProductStatus::Paused], true);
    }

    /**
     * Products that may still move to another category (never published).
     */
    public static function categoryChangeable(Product $product): bool
    {
        return $product->published_at === null && in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected], true);
    }

    /**
     * Products the review step can send to QC.
     */
    public static function submittable(Product $product): bool
    {
        return in_array($product->status, [ProductStatus::Draft, ProductStatus::Rejected], true);
    }
}
