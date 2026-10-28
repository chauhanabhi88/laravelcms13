<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Order\Database\Factories\SellerOrderFactory;
use Modules\Order\Events\SellerOrderStatusChanged;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerWarehouse;

/**
 * The part of a sales order one seller fulfils (SRS §7.1). Its status is authoritative;
 * change it only through OrderStatusService (or OrderCancellationService), which keeps the
 * timestamps and the sales order's derived status in step.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int $seller_id
 * @property string $sub_order_no
 * @property int|null $warehouse_id
 * @property string $status
 * @property Money $items_subtotal
 * @property Money $tax_total
 * @property Money $commission_total
 * @property Money $delivery_charge_share
 * @property string|null $invoice_no
 * @property Carbon|null $invoice_date
 * @property Carbon|null $sla_dispatch_by
 * @property Carbon|null $sla_warned_at
 * @property Carbon|null $expected_delivery_at
 * @property Carbon|null $accepted_at
 * @property int $awb_failures
 * @property Carbon|null $packed_at
 * @property Carbon|null $label_generated_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property string|null $cancelled_by
 * @property Carbon|null $return_window_ends_at
 * @property string|null $receipt_condition
 * @property Carbon|null $receipt_confirmed_at
 * @property string|null $receipt_confirmed_by
 * @property string|null $receipt_note
 * @property list<string>|null $receipt_photos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SellerOrder extends MarketplaceModel
{
    /** @use HasFactory<SellerOrderFactory> */
    use HasFactory;

    use HasStateMachine;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PENDING_ACCEPTANCE = 'pending_acceptance';

    public const STATUS_READY_TO_SHIP = 'ready_to_ship';

    public const STATUS_PACKED = 'packed';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_NDR = 'ndr';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_RTO_INITIATED = 'rto_initiated';

    public const STATUS_RTO_DELIVERED = 'rto_delivered';

    public const STATUS_RETURN_REQUESTED = 'return_requested';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_ACCEPTANCE,
        self::STATUS_READY_TO_SHIP,
        self::STATUS_PACKED,
        self::STATUS_SHIPPED,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_NDR,
        self::STATUS_DELIVERED,
        self::STATUS_RTO_INITIATED,
        self::STATUS_RTO_DELIVERED,
        self::STATUS_RETURN_REQUESTED,
        self::STATUS_RETURNED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Not yet handed to the courier: the customer (and admin) may still cancel.
     */
    public const PRE_SHIPMENT_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_ACCEPTANCE,
        self::STATUS_READY_TO_SHIP,
        self::STATUS_PACKED,
    ];

    /**
     * With the courier, on the way to the customer (or back to the seller).
     */
    public const IN_TRANSIT_STATUSES = [
        self::STATUS_SHIPPED,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_NDR,
        self::STATUS_RTO_INITIATED,
    ];

    /**
     * Reached the customer.
     */
    public const DELIVERED_STATUSES = [
        self::STATUS_DELIVERED,
        self::STATUS_RETURN_REQUESTED,
        self::STATUS_RETURNED,
        self::STATUS_COMPLETED,
    ];

    /**
     * SRS §7.1. M13/M14/M15 drive most of these through OrderStatusService.
     *
     * @var array<string, list<string>>
     */
    protected static array $transitions = [
        self::STATUS_PENDING_PAYMENT => [self::STATUS_PENDING_ACCEPTANCE, self::STATUS_CANCELLED],
        self::STATUS_PENDING_ACCEPTANCE => [self::STATUS_READY_TO_SHIP, self::STATUS_CANCELLED],
        self::STATUS_READY_TO_SHIP => [self::STATUS_PACKED, self::STATUS_CANCELLED],
        self::STATUS_PACKED => [self::STATUS_SHIPPED, self::STATUS_CANCELLED],
        self::STATUS_SHIPPED => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_RTO_INITIATED],
        self::STATUS_OUT_FOR_DELIVERY => [self::STATUS_DELIVERED, self::STATUS_NDR],
        self::STATUS_NDR => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_RTO_INITIATED],
        self::STATUS_RTO_INITIATED => [self::STATUS_RTO_DELIVERED],
        self::STATUS_DELIVERED => [self::STATUS_RETURN_REQUESTED, self::STATUS_COMPLETED],
        self::STATUS_RETURN_REQUESTED => [self::STATUS_RETURNED],
    ];

    /**
     * Every status change announces SellerOrderStatusChanged (after commit).
     *
     * @var array<string, class-string>
     */
    protected array $transitionEvents = [
        self::STATUS_PENDING_ACCEPTANCE => SellerOrderStatusChanged::class,
        self::STATUS_READY_TO_SHIP => SellerOrderStatusChanged::class,
        self::STATUS_PACKED => SellerOrderStatusChanged::class,
        self::STATUS_SHIPPED => SellerOrderStatusChanged::class,
        self::STATUS_OUT_FOR_DELIVERY => SellerOrderStatusChanged::class,
        self::STATUS_NDR => SellerOrderStatusChanged::class,
        self::STATUS_DELIVERED => SellerOrderStatusChanged::class,
        self::STATUS_RTO_INITIATED => SellerOrderStatusChanged::class,
        self::STATUS_RTO_DELIVERED => SellerOrderStatusChanged::class,
        self::STATUS_RETURN_REQUESTED => SellerOrderStatusChanged::class,
        self::STATUS_RETURNED => SellerOrderStatusChanged::class,
        self::STATUS_COMPLETED => SellerOrderStatusChanged::class,
        self::STATUS_CANCELLED => SellerOrderStatusChanged::class,
    ];

    protected $table = 'seller_order';

    protected $fillable = [
        'sales_order_id',
        'seller_id',
        'sub_order_no',
        'warehouse_id',
        'status',
        'items_subtotal',
        'tax_total',
        'commission_total',
        'delivery_charge_share',
        'invoice_no',
        'invoice_date',
        'sla_dispatch_by',
        'sla_warned_at',
        'expected_delivery_at',
        'accepted_at',
        'awb_failures',
        'packed_at',
        'label_generated_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
        'cancelled_by',
        'return_window_ends_at',
        'receipt_condition',
        'receipt_confirmed_at',
        'receipt_confirmed_by',
        'receipt_note',
        'receipt_photos',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'seller_id' => 'integer',
            'warehouse_id' => 'integer',
            'items_subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'commission_total' => MoneyCast::class,
            'delivery_charge_share' => MoneyCast::class,
            'invoice_date' => 'date',
            'sla_dispatch_by' => 'datetime',
            'sla_warned_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'accepted_at' => 'datetime',
            'awb_failures' => 'integer',
            'packed_at' => 'datetime',
            'label_generated_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'return_window_ends_at' => 'datetime',
            'receipt_confirmed_at' => 'datetime',
            'receipt_photos' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
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
     * @return HasMany<SalesOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'seller_order_id')->orderBy('id');
    }

    /**
     * @return HasMany<SalesOrderDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SalesOrderDocument::class, 'seller_order_id')->orderByDesc('id');
    }

    /**
     * @param  Builder<SellerOrder>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    /**
     * Still before dispatch and past its dispatch deadline.
     *
     * @param  Builder<SellerOrder>  $query
     */
    public function scopeSlaBreached(Builder $query, ?Carbon $now = null): void
    {
        $query->whereIn($this->qualifyColumn('status'), [self::STATUS_PENDING_ACCEPTANCE, self::STATUS_READY_TO_SHIP, self::STATUS_PACKED])
            ->where($this->qualifyColumn('sla_dispatch_by'), '<', $now ?? Carbon::now());
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isPreShipment(): bool
    {
        return in_array($this->status, self::PRE_SHIPMENT_STATUSES, true);
    }

    /**
     * Accepted by the seller but the courier booking (AWB) has not gone through yet.
     */
    public function isAwaitingAwb(): bool
    {
        return $this->status === self::STATUS_PENDING_ACCEPTANCE && $this->accepted_at !== null;
    }

    public function hasLabel(): bool
    {
        return $this->label_generated_at !== null;
    }

    /**
     * Back with the seller (RTO) and its condition not confirmed yet.
     */
    public function awaitsReceipt(): bool
    {
        return $this->status === self::STATUS_RTO_DELIVERED && $this->receipt_confirmed_at === null;
    }

    public function isSlaBreached(?Carbon $now = null): bool
    {
        return in_array($this->status, [self::STATUS_PENDING_ACCEPTANCE, self::STATUS_READY_TO_SHIP, self::STATUS_PACKED], true)
            && $this->sla_dispatch_by !== null
            && $this->sla_dispatch_by->lessThan($now ?? Carbon::now());
    }

    protected static function newFactory(): SellerOrderFactory
    {
        return SellerOrderFactory::new();
    }
}
