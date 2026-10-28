<?php

declare(strict_types=1);

namespace Modules\Returns\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Logistics\Models\Shipment;
use Modules\Order\Models\SalesOrder;
use Modules\Order\Models\SellerOrder;
use Modules\Payment\Models\PaymentRefund;
use Modules\Returns\Database\Factories\ReturnRequestFactory;
use Modules\Returns\Events\ReturnStatusChanged;
use Modules\Seller\Models\Seller;

/**
 * A return or exchange of items from one seller order (FR-RET-003, SRS §7.3). Change the
 * status only through ReturnService (it keeps the order items, the seller order and the
 * refund in step). refund_status is the money's own track: a trusted customer is refunded
 * at pickup, before the seller's QC.
 *
 * @property int $id
 * @property string $return_no
 * @property int $sales_order_id
 * @property int $seller_order_id
 * @property int $customer_id
 * @property int $seller_id
 * @property string $type
 * @property string $status
 * @property string $reason_code
 * @property string|null $comment
 * @property string|null $refund_mode
 * @property array<string, string>|null $refund_beneficiary
 * @property Money $refund_amount
 * @property Money $delivery_refund
 * @property string|null $refund_status
 * @property string $refund_on
 * @property array<string, mixed> $pickup_address
 * @property int|null $reverse_shipment_id
 * @property int|null $exchange_seller_order_id
 * @property int $auto_approved
 * @property int $needs_attention
 * @property string|null $attention_reason
 * @property string|null $decided_by
 * @property int|null $decided_by_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property string|null $qc_note
 * @property Carbon|null $pickup_scheduled_at
 * @property Carbon|null $picked_up_at
 * @property Carbon|null $received_at
 * @property Carbon|null $qc_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ReturnRequest extends MarketplaceModel
{
    /** @use HasFactory<ReturnRequestFactory> */
    use HasFactory;

    use HasStateMachine;

    public const TYPE_RETURN = 'return';

    public const TYPE_EXCHANGE = 'exchange';

    public const TYPES = [self::TYPE_RETURN, self::TYPE_EXCHANGE];

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PICKUP_SCHEDULED = 'pickup_scheduled';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_QC_PASSED = 'qc_passed';

    public const STATUS_QC_FAILED = 'qc_failed';

    public const STATUS_REFUND_INITIATED = 'refund_initiated';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_EXCHANGE_ORDER_CREATED = 'exchange_order_created';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PICKUP_SCHEDULED,
        self::STATUS_PICKED_UP,
        self::STATUS_RECEIVED,
        self::STATUS_QC_PASSED,
        self::STATUS_QC_FAILED,
        self::STATUS_REFUND_INITIATED,
        self::STATUS_REFUNDED,
        self::STATUS_EXCHANGE_ORDER_CREATED,
        self::STATUS_CANCELLED,
        self::STATUS_CLOSED,
    ];

    /**
     * Still in progress: the items it holds cannot be returned again.
     */
    public const OPEN_STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_PICKUP_SCHEDULED,
        self::STATUS_PICKED_UP,
        self::STATUS_RECEIVED,
        self::STATUS_QC_PASSED,
        self::STATUS_QC_FAILED,
        self::STATUS_REFUND_INITIATED,
    ];

    /**
     * Ended without the items coming back (the items are the customer's again).
     */
    public const WITHDRAWN_STATUSES = [self::STATUS_REJECTED, self::STATUS_CANCELLED];

    /**
     * The customer may withdraw the request: the courier has not collected it yet.
     */
    public const CANCELLABLE_STATUSES = [self::STATUS_REQUESTED, self::STATUS_APPROVED, self::STATUS_PICKUP_SCHEDULED];

    /**
     * The seller may record the QC result: on its way back or arrived.
     */
    public const QC_STATUSES = [self::STATUS_PICKED_UP, self::STATUS_RECEIVED];

    public const REFUND_INITIATED = 'initiated';

    public const REFUND_REFUNDED = 'refunded';

    public const REFUND_FAILED = 'failed';

    public const REFUND_ON_PICKUP = 'pickup';

    public const REFUND_ON_QC = 'qc';

    /**
     * SRS §7.3. A cancelled pickup goes back to approved for a new one; the courier may deliver
     * without a pickup scan (pickup_scheduled → received); a trusted customer's refund may
     * already be done when QC passes (qc_passed → refunded).
     *
     * @var array<string, list<string>>
     */
    protected static array $transitions = [
        self::STATUS_REQUESTED => [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_APPROVED => [self::STATUS_PICKUP_SCHEDULED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_PICKUP_SCHEDULED => [self::STATUS_PICKED_UP, self::STATUS_RECEIVED, self::STATUS_APPROVED, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP => [self::STATUS_RECEIVED],
        self::STATUS_RECEIVED => [self::STATUS_QC_PASSED, self::STATUS_QC_FAILED],
        self::STATUS_QC_PASSED => [self::STATUS_REFUND_INITIATED, self::STATUS_REFUNDED, self::STATUS_EXCHANGE_ORDER_CREATED],
        self::STATUS_QC_FAILED => [self::STATUS_REFUND_INITIATED, self::STATUS_REFUNDED, self::STATUS_CLOSED],
        self::STATUS_REFUND_INITIATED => [self::STATUS_REFUNDED],
    ];

    /**
     * @var array<string, class-string>
     */
    protected array $transitionEvents = [
        self::STATUS_APPROVED => ReturnStatusChanged::class,
        self::STATUS_REJECTED => ReturnStatusChanged::class,
        self::STATUS_PICKUP_SCHEDULED => ReturnStatusChanged::class,
        self::STATUS_PICKED_UP => ReturnStatusChanged::class,
        self::STATUS_RECEIVED => ReturnStatusChanged::class,
        self::STATUS_QC_PASSED => ReturnStatusChanged::class,
        self::STATUS_QC_FAILED => ReturnStatusChanged::class,
        self::STATUS_REFUND_INITIATED => ReturnStatusChanged::class,
        self::STATUS_REFUNDED => ReturnStatusChanged::class,
        self::STATUS_EXCHANGE_ORDER_CREATED => ReturnStatusChanged::class,
        self::STATUS_CANCELLED => ReturnStatusChanged::class,
        self::STATUS_CLOSED => ReturnStatusChanged::class,
    ];

    protected $table = 'return_request';

    protected $fillable = [
        'return_no',
        'sales_order_id',
        'seller_order_id',
        'customer_id',
        'seller_id',
        'type',
        'status',
        'reason_code',
        'comment',
        'refund_mode',
        'refund_beneficiary',
        'refund_amount',
        'delivery_refund',
        'refund_status',
        'refund_on',
        'pickup_address',
        'reverse_shipment_id',
        'exchange_seller_order_id',
        'auto_approved',
        'needs_attention',
        'attention_reason',
        'decided_by',
        'decided_by_id',
        'decided_at',
        'decision_note',
        'qc_note',
        'pickup_scheduled_at',
        'picked_up_at',
        'received_at',
        'qc_at',
        'refunded_at',
        'cancelled_at',
        'closed_at',
    ];

    /**
     * Bank details never appear in arrays / JSON.
     *
     * @var list<string>
     */
    protected $hidden = ['refund_beneficiary'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'seller_order_id' => 'integer',
            'customer_id' => 'integer',
            'seller_id' => 'integer',
            'refund_beneficiary' => 'encrypted:array',
            'refund_amount' => MoneyCast::class,
            'delivery_refund' => MoneyCast::class,
            'pickup_address' => 'array',
            'reverse_shipment_id' => 'integer',
            'exchange_seller_order_id' => 'integer',
            'auto_approved' => 'integer',
            'needs_attention' => 'integer',
            'decided_by_id' => 'integer',
            'decided_at' => 'datetime',
            'pickup_scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'received_at' => 'datetime',
            'qc_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ReturnRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class, 'return_request_id')->orderBy('id');
    }

    /**
     * @return HasMany<ReturnRequestImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ReturnRequestImage::class, 'return_request_id')->orderBy('id');
    }

    /**
     * @return HasMany<ReturnClaim, $this>
     */
    public function claims(): HasMany
    {
        return $this->hasMany(ReturnClaim::class, 'return_request_id')->orderByDesc('id');
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'return_request_id')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function exchangeSellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'exchange_seller_order_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function reverseShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'reverse_shipment_id');
    }

    /**
     * @param  Builder<ReturnRequest>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where($this->qualifyColumn('customer_id'), $customerId);
    }

    /**
     * @param  Builder<ReturnRequest>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    /**
     * @param  Builder<ReturnRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('status'), self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isExchange(): bool
    {
        return $this->type === self::TYPE_EXCHANGE;
    }

    public function isCancellableByCustomer(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES, true);
    }

    public function awaitsQc(): bool
    {
        return in_array($this->status, self::QC_STATUSES, true);
    }

    public function refundsOnPickup(): bool
    {
        return $this->refund_on === self::REFUND_ON_PICKUP;
    }

    /**
     * A refund was started (or done) for this return.
     */
    public function hasRefund(): bool
    {
        return in_array($this->refund_status, [self::REFUND_INITIATED, self::REFUND_REFUNDED], true);
    }

    public function isAutoApproved(): bool
    {
        return $this->auto_approved === (int) config('core.yes');
    }

    public function needsAttention(): bool
    {
        return $this->needs_attention === (int) config('core.yes');
    }

    /**
     * The reason puts the fault with the seller (defective, damaged, wrong or missing item…).
     */
    public function isSellerFault(): bool
    {
        return self::sellerFaultReason($this->reason_code);
    }

    public static function sellerFaultReason(string $reason): bool
    {
        return in_array($reason, (array) config('returns.seller_fault_reasons', []), true);
    }

    /**
     * Photos are required for this reason (optional for e.g. a size swap).
     */
    public static function photosRequiredFor(string $reason): bool
    {
        return in_array($reason, (array) config('returns.photo_required_reasons', []), true);
    }

    /**
     * Masked refund details for screens: "XXXXXX1234 / HDFC0001234" or "name@upi".
     */
    public function maskedBeneficiary(): ?string
    {
        $beneficiary = (array) ($this->refund_beneficiary ?? []);

        if (isset($beneficiary['vpa'])) {
            return (string) $beneficiary['vpa'];
        }

        if (isset($beneficiary['account_number'])) {
            $number = (string) $beneficiary['account_number'];

            return str_repeat('X', max(0, strlen($number) - 4)).substr($number, -4).(isset($beneficiary['ifsc']) ? ' / '.$beneficiary['ifsc'] : '');
        }

        return null;
    }

    /**
     * The pickup address as one line.
     */
    public function pickupSummary(): string
    {
        $address = (array) $this->pickup_address;

        return collect([$address['address_line1'] ?? null, $address['address_line2'] ?? null, $address['landmark'] ?? null, $address['city_name'] ?? null, $address['state_name'] ?? null, $address['pincode'] ?? null])
            ->filter(fn ($part): bool => $part !== null && trim((string) $part) !== '')
            ->implode(', ');
    }

    protected static function newFactory(): ReturnRequestFactory
    {
        return ReturnRequestFactory::new();
    }
}
