<?php

declare(strict_types=1);

namespace Modules\Review\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Modules\Customer\Models\Customer;
use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewVote;

/**
 * Published reviews of a product for the full reviews page and the API (FR-REV-005): filter by
 * stars and "with photos", sort by most helpful (default), latest, highest or lowest rating.
 */
class ReviewListService
{
    /**
     * @return array{rating: int|null, with_photos: bool, sort: string}
     */
    public function filters(Request $request): array
    {
        $rating = $request->integer('rating');
        $sorts = (array) config('review.sorts', ['helpful']);
        $sort = (string) $request->query('sort', '');

        return [
            'rating' => $rating >= 1 && $rating <= 5 ? $rating : null,
            'with_photos' => $request->boolean('with_photos'),
            'sort' => in_array($sort, $sorts, true) ? $sort : (string) $sorts[0],
        ];
    }

    /**
     * @param  array{rating: int|null, with_photos: bool, sort: string}  $filters
     * @return LengthAwarePaginator<int, ProductReview>
     */
    public function forProduct(int $productId, array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = ProductReview::query()
            ->published()
            ->where('product_id', $productId)
            ->with('images')
            ->when($filters['rating'] !== null, fn ($query) => $query->where('rating', $filters['rating']))
            ->when($filters['with_photos'], fn ($query) => $query->where('has_images', config('core.yes')));

        match ($filters['sort']) {
            'latest' => $query->orderByDesc('published_at'),
            'rating_high' => $query->orderByDesc('rating')->orderByDesc('published_at'),
            'rating_low' => $query->orderBy('rating')->orderByDesc('published_at'),
            default => $query->orderByDesc('helpful_count')->orderByRaw('(body IS NOT NULL) DESC')->orderByDesc('published_at'),
        };

        return $query->orderByDesc('id')->paginate(min(50, max(1, $perPage ?? (int) config('review.per_page', 10))));
    }

    /**
     * Ids of the given reviews the customer found helpful.
     *
     * @param  list<int>  $reviewIds
     * @return list<int>
     */
    public function votedIds(?Customer $customer, array $reviewIds): array
    {
        if ($customer === null || $reviewIds === []) {
            return [];
        }

        return ProductReviewVote::query()
            ->where('customer_id', $customer->id)
            ->whereIn('product_review_id', $reviewIds)
            ->pluck('product_review_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
