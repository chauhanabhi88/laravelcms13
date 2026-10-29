<?php

declare(strict_types=1);

namespace Modules\Review\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Review\Models\ProductReview;
use Modules\Review\Support\ReviewPresenter;

/**
 * A review for the apps: the storefront card plus, for the author's own reviews, the status
 * and the moderator's reason. Load "images" first.
 *
 * @mixin ProductReview
 */
class ReviewResource extends JsonResource
{
    /**
     * @param  list<int>  $votedIds  reviews the signed-in customer found helpful
     */
    public function __construct(ProductReview $resource, private readonly array $votedIds = [], private readonly bool $owner = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ProductReview $review */
        $review = $this->resource;
        $data = ReviewPresenter::card($review) + [
            'product_id' => $review->product_id,
            'voted' => in_array($review->id, $this->votedIds, true),
        ];

        if ($this->owner) {
            $data += [
                'order_item_id' => $review->sales_order_item_id,
                'status' => $review->status,
                'rejection_reason' => $review->isRejected() ? $review->rejection_reason : null,
                'created_at' => $review->created_at?->toIso8601String(),
            ];
        }

        return $data;
    }
}
