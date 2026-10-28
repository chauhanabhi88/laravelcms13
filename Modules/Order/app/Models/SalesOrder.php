<?php

declare(strict_types=1);

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Cart\Support\CartSettings;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\Database\Factories\SalesOrderFactory;
use Modules\Payment\Contracts\PayableOrder;
use Modules\Payment\Models\Payment;

/**
 * The customer's order (FR-ORD-003). Its status is derived from the seller orders by
 * OrderStatusService::refresh(); the money columns are the snapshot taken at placement.
 * It is also what the Payment module pays (PayableOrder).
 *
 * @property int $id
 * @property string $order_no
 * @property int $customer_id
 * @property string $status
 * @property string $payment_method
 * @property string|null $payment_status
 * @property int $items_count
 * @property Money $total_mrp
 * @property Money $total_discount
 * @property string|null $coupon_code
 * @property Money $coupon_discount
 * @property Money $delivery_charge
 * @property Money $cod_fee
 * @property Money $grand_total
 * @property int $is_reseller_order
 * @property Money $reseller_margin_total
 * @property int $cod_allowed
 * @property string|null $placed_ip
 * @property string|null $user_agent
 * @property string $channel
 * @property string $placed_token
 * @property Carbon|null $placed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SalesOrder extends MarketplaceModel implements PayableOrder
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    use HasStateMachine;

    public const STATUS_PLACED = 'placed';

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PENDING_COD_VERIFICATION = 'pending_cod_verification';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PARTIALLY_SHIPPED = 'partially_shipped';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PARTIALLY_CANCELLED = 'partially_cancelled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PLACED,
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_COD_VERIFICATION,
        self::STATUS_CONFIRMED,
        self::STATUS_PARTIALLY_SHIPPED,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_PARTIALLY_CANCELLED,
        self::STATUS_CANCELLED,
        self::STATUS_COMPLETED,
    ];

    /**
     * Placed but not yet confirmed: waiting for the prepaid payment or the COD OTP.
     */
    public const AWAITING_PAYMENT_STATUSES = [
        self::STATUS_PLACED,
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_COD_VERIFICATION,
    ];

    /**
     * Finished for the customer: nothing more will ship.
     */
    public const CLOSED_STATUSES = [self::STATUS_CANCELLED, self::STATUS_DELIVERED, self::STATUS_COMPLETED];

    /**
     * The status is derived from the seller orders, so any change is allowed; the history
     * row still records every move.
     *
     * @var array<string, list<string>>
     */
    protected static array $transitions = ['*' => self::STATUSES];

    protected $table = 'sales_order';

    protected $fillable = [
        'order_no',
        'customer_id',
        'status',
        'payment_method',
        'payment_status',
        'items_count',
        'total_mrp',
        'total_discount',
        'coupon_code',
        'coupon_discount',
        'delivery_charge',
        'cod_fee',
        'grand_total',
        'is_reseller_order',
        'reseller_margin_total',
        'cod_allowed',
        'placed_ip',
        'user_agent',
        'channel',
        'placed_token',
        'placed_at',
        'cancelled_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'items_count' => 'integer',
            'total_mrp' => MoneyCast::class,
            'total_discount' => MoneyCast::class,
            'coupon_discount' => MoneyCast::class,
            'delivery_charge' => MoneyCast::class,
            'cod_fee' => MoneyCast::class,
            'grand_total' => MoneyCast::class,
            'reseller_margin_total' => MoneyCast::class,
            'is_reseller_order' => 'integer',
            'cod_allowed' => 'integer',
            'placed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    /**
     * @return HasMany<SellerOrder, $this>
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class, 'sales_order_id')->orderBy('id');
    }

    /**
     * @return HasMany<SalesOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id')->orderBy('id');
    }

    /**
     * @return HasMany<SalesOrderAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(SalesOrderAddress::class, 'sales_order_id');
    }

    /**
     * @return HasOne<SalesOrderAddress, $this>
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(SalesOrderAddress::class, 'sales_order_id')->where('type', SalesOrderAddress::TYPE_SHIPPING);
    }

    /**
     * @return HasOne<SalesOrderAddress, $this>
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(SalesOrderAddress::class, 'sales_order_id')->where('type', SalesOrderAddress::TYPE_BILLING);
    }

    /**
     * @return HasMany<SalesOrderNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(SalesOrderNote::class, 'sales_order_id')->orderByDesc('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'sales_order_id')->orderByDesc('id');
    }

    /**
     * @param  Builder<SalesOrder>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where($this->qualifyColumn('customer_id'), $customerId);
    }

    public function isCod(): bool
    {
        return $this->payment_method === Payment::METHOD_COD;
    }

    public function isResellerOrder(): bool
    {
        return $this->is_reseller_order === (int) config('core.yes');
    }

    public function isAwaitingPayment(): bool
    {
        return in_array($this->status, self::AWAITING_PAYMENT_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * Σ selling price × qty of the order as placed.
     */
    public function itemsSubtotal(): Money
    {
        return $this->total_mrp->minus($this->total_discount);
    }

    /**
     * The reseller's margin if their customer pays on delivery: Σ (customer price − price) × qty.
     */
    public function resellerCodMargin(): Money
    {
        if (! $this->isResellerOrder()) {
            return Money::zero();
        }

        $this->loadMissing('items');

        return $this->items->reduce(
            fn (Money $sum, SalesOrderItem $item): Money => $sum->plus($item->potentialMargin()),
            Money::zero(),
        );
    }

    /**
     * What the buyer pays without the COD-only parts: items − coupon + delivery.
     */
    public function prepaidTotal(): Money
    {
        return $this->itemsSubtotal()->minus($this->coupon_discount)->plus($this->delivery_charge);
    }

    /**
     * What the buyer pays on delivery: the prepaid total plus the COD fee and, for a reseller
     * order, their customer's prices.
     */
    public function codTotal(): Money
    {
        $fee = $this->isCod() ? $this->cod_fee : CartSettings::codFee();

        return $this->prepaidTotal()->plus($fee)->plus($this->resellerCodMargin());
    }

    public function payableOrderId(): int
    {
        return $this->id;
    }

    public function payableOrderNo(): string
    {
        return $this->order_no;
    }

    public function payableCustomer(): Customer
    {
        $this->loadMissing('customer');

        return $this->customer;
    }

    public function payableAmount(string $method): Money
    {
        if ($method === Payment::METHOD_COD) {
            return $this->isCod() ? $this->grand_total : $this->codTotal();
        }

        return $this->isCod() ? $this->prepaidTotal() : $this->grand_total;
    }

    public function deliveryPincode(): ?string
    {
        $this->loadMissing('shippingAddress');

        return $this->shippingAddress?->pincode;
    }

    public function allItemsAllowCod(): bool
    {
        return $this->cod_allowed === (int) config('core.yes');
    }

    public function confirmationUrl(): string
    {
        return frontRoute('checkout.success', ['orderNo' => $this->order_no]);
    }

    protected static function newFactory(): SalesOrderFactory
    {
        return SalesOrderFactory::new();
    }
}
