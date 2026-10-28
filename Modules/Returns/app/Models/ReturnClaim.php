<?php

declare(strict_types=1);

namespace Modules\Returns\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Order\Models\SellerOrder;
use Modules\Returns\Database\Factories\ReturnClaimFactory;
use Modules\Seller\Models\Seller;

/**
 * A seller's dispute of a returned package (plan M15 §5.6): a customer return the seller
 * found damaged, wrong, used or incomplete, or an RTO package confirmed as damaged in the
 * order desk (M14). Written by ReturnClaimService only; M17 credits accepted claims.
 *
 * @property int $id
 * @property int|null $return_request_id
 * @property int $seller_order_id
 * @property int $seller_id
 * @property string $source
 * @property string $reason
 * @property string|null $description
 * @property list<string>|null $images
 * @property string $status
 * @property Money|null $resolution_amount
 * @property string|null $decision_note
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReturnClaim extends MarketplaceModel
{
    /** @use HasFactory<ReturnClaimFactory> */
    use HasFactory;

    public const SOURCE_RETURN = 'return';

    public const SOURCE_RTO = 'rto';

    public const STATUS_OPEN = 'open';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_ACCEPTED, self::STATUS_REJECTED];

    protected $table = 'return_claim';

    protected $fillable = [
        'return_request_id',
        'seller_order_id',
        'seller_id',
        'source',
        'reason',
        'description',
        'images',
        'status',
        'resolution_amount',
        'decision_note',
        'decided_by',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_request_id' => 'integer',
            'seller_order_id' => 'integer',
            'seller_id' => 'integer',
            'images' => 'array',
            'resolution_amount' => MoneyCast::class,
            'decided_by' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ReturnRequest, $this>
     */
    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_request_id');
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @param  Builder<ReturnClaim>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    protected static function newFactory(): ReturnClaimFactory
    {
        return ReturnClaimFactory::new();
    }
}
