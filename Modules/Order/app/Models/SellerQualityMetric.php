<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Models\Seller;

/**
 * Daily fulfilment counts of one seller (FR-FUL-008), written by SnapshotSellerQualityMetrics.
 *
 * @property int $id
 * @property int $seller_id
 * @property Carbon $date
 * @property int $orders
 * @property int $cancelled_by_seller
 * @property int $late_dispatch
 * @property int $returns
 * @property int $rto
 * @property string|null $rating_avg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SellerQualityMetric extends MarketplaceModel
{
    protected $table = 'seller_quality_metric';

    protected $fillable = ['seller_id', 'date', 'orders', 'cancelled_by_seller', 'late_dispatch', 'returns', 'rto', 'rating_avg'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'date' => 'date',
            'orders' => 'integer',
            'cancelled_by_seller' => 'integer',
            'late_dispatch' => 'integer',
            'returns' => 'integer',
            'rto' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @param  Builder<SellerQualityMetric>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }
}
