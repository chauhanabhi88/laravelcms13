<?php

declare(strict_types=1);

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * Append-only stock ledger row, written only by InventoryService.
 *
 * @property int $id
 * @property int $variant_id
 * @property int $product_id
 * @property string $type
 * @property int $quantity
 * @property string $bucket
 * @property int $stock_after
 * @property int $reserved_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property string|null $note
 * @property Carbon $created_at
 */
class InventoryMovement extends MarketplaceModel
{
    public const TYPE_OPENING = 'opening';

    public const TYPE_ADJUST = 'adjust';

    public const TYPE_RESERVE = 'reserve';

    public const TYPE_RELEASE = 'release';

    public const TYPE_COMMIT = 'commit';

    public const TYPE_RESTOCK = 'restock';

    public const TYPE_CORRECTION = 'correction';

    public const TYPES = [
        self::TYPE_OPENING,
        self::TYPE_ADJUST,
        self::TYPE_RESERVE,
        self::TYPE_RELEASE,
        self::TYPE_COMMIT,
        self::TYPE_RESTOCK,
        self::TYPE_CORRECTION,
    ];

    public const BUCKET_STOCK = 'stock';

    public const BUCKET_RESERVED = 'reserved';

    public const UPDATED_AT = null;

    protected $table = 'inventory_movement';

    protected $fillable = [
        'variant_id',
        'product_id',
        'type',
        'quantity',
        'bucket',
        'stock_after',
        'reserved_after',
        'reference_type',
        'reference_id',
        'actor_type',
        'actor_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'stock_after' => 'integer',
            'reserved_after' => 'integer',
            'reference_id' => 'integer',
            'actor_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return trans('product::product.movement_types.'.$this->type);
    }
}
