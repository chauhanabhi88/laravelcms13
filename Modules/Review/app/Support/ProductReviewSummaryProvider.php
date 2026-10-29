<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Modules\Product\Models\Product;
use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewImage;
use Modules\Review\Services\ReviewAggregateService;
use Modules\Storefront\Contracts\ReviewSummaryProvider;
use Modules\Storefront\DTO\ReviewSummary;

/**
 * The product page's ratings block (FR-PDP-008 / FR-REV-005), bound over the Storefront's null
 * provider: average, count and histogram from the product's aggregates, the latest review
 * photos, the top reviews (with text first, then most helpful, then newest) and the link to
 * the full reviews page. Cached per product (ReviewSummaryCache).
 */
class ProductReviewSummaryProvider implements ReviewSummaryProvider
{
    public function __construct(private readonly ReviewAggregateService $aggregates) {}

    public function forProduct(int $productId): ReviewSummary
    {
        $data = ReviewSummaryCache::remember($productId, fn (): array => $this->build($productId));

        if (($data['count'] ?? 0) === 0) {
            return ReviewSummary::empty();
        }

        return new ReviewSummary(
            (float) $data['average'],
            (int) $data['count'],
            $data['histogram'],
            $data['photos'],
            $data['top'],
            frontRoute('storefront.product.reviews', ['slugId' => $data['slug'].'-'.$productId]),
        );
    }

    /**
     * @return array{count: int, average?: float, histogram?: array<int, int>, photos?: list<array<string, mixed>>, top?: list<array<string, mixed>>, slug?: string}
     */
    protected function build(int $productId): array
    {
        $product = Product::query()->withTrashed()->find($productId, ['id', 'slug', 'rating_avg', 'rating_count', 'rating_histogram']);

        if ($product === null || (int) $product->rating_count === 0) {
            return ['count' => 0];
        }

        $photos = ProductReviewImage::query()
            ->select('product_review_image.*')
            ->join('product_review', 'product_review.id', '=', 'product_review_image.product_review_id')
            ->where('product_review.product_id', $productId)
            ->where('product_review.status', ProductReview::STATUS_PUBLISHED)
            ->orderByDesc('product_review.published_at')
            ->orderBy('product_review_image.position')
            ->limit((int) config('review.summary.photos', 10))
            ->get()
            ->map(fn (ProductReviewImage $image): array => ['url' => (string) $image->url(), 'thumb' => $image->thumbUrl(), 'alt' => trans('review::front.summary.photo_alt')])
            ->all();

        $top = ProductReview::query()
            ->published()
            ->where('product_id', $productId)
            ->with('images')
            ->orderByRaw('(body IS NOT NULL) DESC')
            ->orderByDesc('helpful_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit((int) config('review.summary.top', 3))
            ->get()
            ->map(fn (ProductReview $review): array => ReviewPresenter::card($review))
            ->all();

        return [
            'count' => (int) $product->rating_count,
            'average' => (float) $product->rating_avg,
            'histogram' => $this->aggregates->histogram($product->rating_histogram),
            'photos' => $photos,
            'top' => $top,
            'slug' => $product->slug,
        ];
    }
}
