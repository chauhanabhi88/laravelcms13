<?php

declare(strict_types=1);

namespace Modules\Review\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A customer's "helpful" vote on a review. The table's key is (product_review_id,
 * customer_id), which Eloquent cannot address: ReviewVoteService writes it with query-builder
 * inserts and deletes; this model is for reads.
 *
 * @property int $product_review_id
 * @property int $customer_id
 * @property Carbon|null $created_at
 */
class ProductReviewVote extends MarketplaceModel
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $table = 'product_review_vote';

    protected $primaryKey = 'product_review_id';

    protected $fillable = ['product_review_id', 'customer_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_review_id' => 'integer',
            'customer_id' => 'integer',
        ];
    }
}
