<?php

declare(strict_types=1);

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Customer\Models\Customer;

/**
 * A customer's abuse report on a review (one per customer and review).
 *
 * @property int $id
 * @property int $product_review_id
 * @property int $customer_id
 * @property string $reason
 * @property string|null $comment
 * @property Carbon|null $created_at
 */
class ProductReviewReport extends MarketplaceModel
{
    public const UPDATED_AT = null;

    protected $table = 'product_review_report';

    protected $fillable = ['product_review_id', 'customer_id', 'reason', 'comment'];

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

    /**
     * @return BelongsTo<ProductReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
