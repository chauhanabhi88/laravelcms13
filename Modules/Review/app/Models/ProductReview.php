<?php

declare(strict_types=1);

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SalesOrderItem;
use Modules\Product\Models\Product;
use Modules\Review\Database\Factories\ProductReviewFactory;
use Modules\Seller\Models\Seller;

/**
 * A verified buyer's rating (and optional title, text and photos) of one delivered order item
 * (FR-REV-001). Write it only through ReviewService / ReviewModerationService /
 * SellerReplyService: they run the automatic checks and keep the product and seller
 * aggregates in step.
 *
 * Statuses: pending (waits for a moderator) → published | rejected; published → hidden (by a
 * moderator) or back to pending after an edit that fails the checks; rejected → pending |
 * published after an edit; hidden → published (moderator).
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $sales_order_item_id
 * @property int $sales_order_id
 * @property int $customer_id
 * @property int $seller_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $body
 * @property string $author_name
 * @property string|null $variant_label
 * @property string $status
 * @property list<string>|null $moderation_flags
 * @property string|null $rejection_reason
 * @property int $helpful_count
 * @property int $report_count
 * @property int $has_images
 * @property int $is_returned
 * @property string|null $seller_reply
 * @property Carbon|null $seller_replied_at
 * @property int|null $seller_replied_by
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property Carbon|null $edited_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductReview extends MarketplaceModel
{
    /** @use HasFactory<ProductReviewFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PUBLISHED,
        self::STATUS_REJECTED,
        self::STATUS_HIDDEN,
    ];

    /**
     * Not a stored status: ReviewStatusChanged::$to of a deleted review.
     */
    public const DELETED = 'deleted';

    public const FLAG_PROFANITY = 'profanity';

    public const FLAG_LINK = 'link';

    public const FLAG_CONTACT = 'contact';

    public const FLAG_DUPLICATE = 'duplicate';

    public const FLAG_TOO_SHORT = 'too_short';

    protected $table = 'product_review';

    protected $fillable = [
        'product_id',
        'variant_id',
        'sales_order_item_id',
        'sales_order_id',
        'customer_id',
        'seller_id',
        'rating',
        'title',
        'body',
        'author_name',
        'variant_label',
        'status',
        'moderation_flags',
        'rejection_reason',
        'has_images',
        'is_returned',
        'edited_at',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'variant_id' => 'integer',
            'sales_order_item_id' => 'integer',
            'sales_order_id' => 'integer',
            'customer_id' => 'integer',
            'seller_id' => 'integer',
            'rating' => 'integer',
            'moderation_flags' => 'array',
            'helpful_count' => 'integer',
            'report_count' => 'integer',
            'has_images' => 'integer',
            'is_returned' => 'integer',
            'seller_replied_at' => 'datetime',
            'seller_replied_by' => 'integer',
            'moderated_by' => 'integer',
            'moderated_at' => 'datetime',
            'edited_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<SalesOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @return HasMany<ProductReviewImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductReviewImage::class, 'product_review_id')->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<ProductReviewReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ProductReviewReport::class, 'product_review_id')->orderByDesc('id');
    }

    /**
     * @param  Builder<ProductReview>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * @param  Builder<ProductReview>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where('customer_id', $customerId);
    }

    /**
     * @param  Builder<ProductReview>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where('seller_id', $sellerId);
    }

    /**
     * The moderation queue: waiting reviews plus published ones reported $threshold times or more.
     *
     * @param  Builder<ProductReview>  $query
     */
    public function scopeNeedsModeration(Builder $query, int $threshold): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('status', self::STATUS_PENDING)
            ->orWhere(fn (Builder $reported) => $reported->where('status', self::STATUS_PUBLISHED)->where('report_count', '>=', $threshold)));
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasImages(): bool
    {
        return $this->has_images === (int) config('core.yes');
    }

    /**
     * The item went back to the seller before the review (allowed, shown as "Returned").
     */
    public function isReturned(): bool
    {
        return $this->is_returned === (int) config('core.yes');
    }

    public function hasSellerReply(): bool
    {
        return $this->seller_reply !== null && $this->seller_reply !== '';
    }

    /**
     * @return list<string>
     */
    public function flags(): array
    {
        return array_values(array_map('strval', (array) $this->moderation_flags));
    }

    /**
     * Last moment the customer may still edit the review.
     */
    public function editableUntil(int $windowDays): Carbon
    {
        return ($this->created_at ?? now())->copy()->addDays($windowDays)->endOfDay();
    }

    protected static function newFactory(): ProductReviewFactory
    {
        return ProductReviewFactory::new();
    }
}
