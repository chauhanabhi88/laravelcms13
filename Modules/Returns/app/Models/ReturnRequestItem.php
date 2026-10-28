<?php

declare(strict_types=1);

namespace Modules\Returns\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrderItem;
use Modules\Product\Models\ProductVariant;

/**
 * One order item (and quantity) of a return; for an exchange, the variant wanted instead.
 *
 * @property int $id
 * @property int $return_request_id
 * @property int $sales_order_item_id
 * @property int $quantity
 * @property int|null $exchange_variant_id
 * @property Money $unit_refund
 * @property Money $line_refund
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReturnRequestItem extends MarketplaceModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'return_request_item';

    protected $fillable = [
        'return_request_id',
        'sales_order_item_id',
        'quantity',
        'exchange_variant_id',
        'unit_refund',
        'line_refund',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'return_request_id' => 'integer',
            'sales_order_item_id' => 'integer',
            'quantity' => 'integer',
            'exchange_variant_id' => 'integer',
            'unit_refund' => MoneyCast::class,
            'line_refund' => MoneyCast::class,
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
     * @return BelongsTo<SalesOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function exchangeVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'exchange_variant_id');
    }
}
