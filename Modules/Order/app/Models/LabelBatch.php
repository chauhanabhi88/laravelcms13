<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Seller\Models\Seller;

/**
 * One "Generate labels" run of the supplier panel (FR-FUL-004). GenerateLabelBatch fills the
 * file (packing slips PDF, or a ZIP with the courier labels) on the private disk; the seller
 * downloads it from the order desk.
 *
 * @property int $id
 * @property int $seller_id
 * @property int|null $created_by
 * @property string $status
 * @property list<int> $seller_order_ids
 * @property int $orders_count
 * @property string $label_format
 * @property string|null $path
 * @property string|null $file_name
 * @property list<array{courier: string, url: string}>|null $provider_labels
 * @property string|null $error
 * @property Carbon|null $generated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LabelBatch extends MarketplaceModel
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const FORMAT_A4 = 'a4_4up';

    public const FORMAT_THERMAL = 'thermal_4x6';

    public const FORMATS = [self::FORMAT_A4, self::FORMAT_THERMAL];

    protected $table = 'label_batch';

    protected $fillable = [
        'seller_id',
        'created_by',
        'status',
        'seller_order_ids',
        'orders_count',
        'label_format',
        'path',
        'file_name',
        'provider_labels',
        'error',
        'generated_at',
    ];

    protected $hidden = ['path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'created_by' => 'integer',
            'seller_order_ids' => 'array',
            'orders_count' => 'integer',
            'provider_labels' => 'array',
            'generated_at' => 'datetime',
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
     * @return HasMany<SalesOrderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SalesOrderDocument::class, 'label_batch_id');
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->path !== null;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_PROCESSING], true);
    }
}
