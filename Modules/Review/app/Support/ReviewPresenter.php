<?php

declare(strict_types=1);

namespace Modules\Review\Support;

use Modules\Review\Models\ProductReview;
use Modules\Review\Models\ProductReviewImage;

/**
 * One review as the storefront, the product page summary and the API show it (plain arrays,
 * safe to cache). Load "images" first.
 *
 * @phpstan-type Photo array{url: string, thumb: string|null, alt: string|null}
 * @phpstan-type Card array{id: int, author: string, rating: int, title: string|null, body: string, date: string|null, published_at: string|null, variant: string|null, verified: bool, helpful_count: int, photos: list<Photo>, seller_reply: array{text: string, date: string|null}|null}
 */
final class ReviewPresenter
{
    /**
     * @return Card
     */
    public static function card(ProductReview $review): array
    {
        return [
            'id' => $review->id,
            'author' => $review->author_name,
            'rating' => $review->rating,
            'title' => $review->title,
            'body' => (string) $review->body,
            'date' => ($review->published_at ?? $review->created_at)?->translatedFormat('j M Y'),
            'published_at' => ($review->published_at ?? $review->created_at)?->toIso8601String(),
            'variant' => $review->variant_label,
            'verified' => true,
            'helpful_count' => $review->helpful_count,
            'photos' => self::photos($review),
            'seller_reply' => $review->hasSellerReply()
                ? ['text' => (string) $review->seller_reply, 'date' => $review->seller_replied_at?->translatedFormat('j M Y')]
                : null,
        ];
    }

    /**
     * @return list<Photo>
     */
    public static function photos(ProductReview $review): array
    {
        $alt = trans('review::front.card.photo_alt', ['author' => $review->author_name]);

        return $review->images
            ->map(fn (ProductReviewImage $image): array => ['url' => (string) $image->url(), 'thumb' => $image->thumbUrl(), 'alt' => $alt])
            ->values()
            ->all();
    }
}
