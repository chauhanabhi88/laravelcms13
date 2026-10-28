<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;

/**
 * Packed shipments of one seller warehouse and one courier handed over on a pickup date
 * (FR-FUL-005, plan M14 §3.1), with our manifest number and the courier's manifest. Created by
 * PickupBatchService; tracking moves it to picked_up / partially_picked.
 *
 * @property int $id
 * @property int $seller_id
 * @property int|null $warehouse_id
 * @property int|null $courier_id
 * @property string|null $carrier_name
 * @property Carbon $pickup_date
 * @property string $manifest_no
 * @property string|null $manifest_url
 * @property string|null $pickup_reference
 * @property string $status
 * @property int $shipments_count
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PickupBatch extends MarketplaceModel
{
    public const STATUS_OPEN = 'open';

    public const STATUS_MANIFESTED = 'manifested';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_PARTIALLY_PICKED = 'partially_picked';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_MANIFESTED, self::STATUS_PICKED_UP, self::STATUS_PARTIALLY_PICKED];

    protected $table = 'pickup_batch';

    protected $fillable = [
        'seller_id',
        'warehouse_id',
        'courier_id',
        'carrier_name',
        'pickup_date',
        'manifest_no',
        'manifest_url',
        'pickup_reference',
        'status',
        'shipments_count',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'warehouse_id' => 'integer',
            'courier_id' => 'integer',
            'pickup_date' => 'date',
            'shipments_count' => 'integer',
            'created_by' => 'integer',
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
     * @return BelongsTo<SellerWarehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(SellerWarehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<Courier, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'pickup_batch_id')->orderBy('id');
    }

    /**
     * @param  Builder<PickupBatch>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }
}
