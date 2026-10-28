<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\MarketplaceModel;
use Modules\Order\Models\SalesOrderItem;

/**
 * An order line packed in a shipment (composite key shipment_id + sales_order_item_id).
 *
 * @property int $shipment_id
 * @property int $sales_order_item_id
 * @property int $quantity
 */
class ShipmentItem extends MarketplaceModel
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'shipment_item';

    protected $primaryKey = 'sales_order_item_id';

    protected $fillable = ['shipment_id', 'sales_order_item_id', 'quantity'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'sales_order_item_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * @return BelongsTo<SalesOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
