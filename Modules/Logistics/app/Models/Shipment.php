<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Logistics\Database\Factories\ShipmentFactory;
use Modules\Order\Models\SellerOrder;

/**
 * One package of a seller order (FR-LOG-005). Its status only moves forward (plus the NDR and
 * RTO branches, see canAdvanceTo()); change it only through ShipmentService / TrackingService,
 * which keep the seller order in step. shipment_event holds the scan history.
 *
 * @property int $id
 * @property int $seller_order_id
 * @property string $direction
 * @property int|null $return_request_id
 * @property int|null $courier_id
 * @property int|null $courier_rule_id
 * @property int|null $pickup_batch_id
 * @property string|null $strategy
 * @property string|null $carrier_id
 * @property string|null $carrier_name
 * @property string|null $provider_order_id
 * @property string|null $provider_shipment_id
 * @property string|null $awb
 * @property string $status
 * @property string $payment_mode
 * @property Money $cod_amount
 * @property int $weight_grams
 * @property string|null $length_cm
 * @property string|null $breadth_cm
 * @property string|null $height_cm
 * @property int|null $charged_weight_grams
 * @property Money $freight_charge
 * @property Money $cod_charge
 * @property Money $rto_charge
 * @property string|null $label_url
 * @property string|null $manifest_url
 * @property string|null $invoice_url
 * @property string|null $pickup_location_code
 * @property array<string, mixed>|null $pickup_address
 * @property Carbon|null $pickup_scheduled_at
 * @property Carbon|null $picked_up_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $rto_delivered_at
 * @property Carbon|null $edd
 * @property string|null $tracking_url
 * @property int $attempts
 * @property int $needs_attention
 * @property string|null $attention_reason
 * @property string|null $last_status_label
 * @property Carbon|null $last_event_at
 * @property Carbon|null $last_synced_at
 * @property int|null $rto_restocked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Shipment extends MarketplaceModel
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    public const DIRECTION_FORWARD = 'forward';

    public const DIRECTION_REVERSE = 'reverse';

    public const PAYMENT_COD = 'cod';

    public const PAYMENT_PREPAID = 'prepaid';

    public const STATUS_CREATED = 'created';

    public const STATUS_AWB_ASSIGNED = 'awb_assigned';

    public const STATUS_LABEL_GENERATED = 'label_generated';

    public const STATUS_PICKUP_SCHEDULED = 'pickup_scheduled';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_NDR = 'ndr';

    public const STATUS_RTO_INITIATED = 'rto_initiated';

    public const STATUS_RTO_IN_TRANSIT = 'rto_in_transit';

    public const STATUS_RTO_DELIVERED = 'rto_delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LOST = 'lost';

    /**
     * Progress order of the statuses. NDR sits with out_for_delivery (a re-attempt goes back
     * to out_for_delivery); the RTO branch ranks above delivery.
     */
    public const RANKS = [
        self::STATUS_CREATED => 0,
        self::STATUS_AWB_ASSIGNED => 1,
        self::STATUS_LABEL_GENERATED => 2,
        self::STATUS_PICKUP_SCHEDULED => 3,
        self::STATUS_PICKED_UP => 4,
        self::STATUS_IN_TRANSIT => 5,
        self::STATUS_OUT_FOR_DELIVERY => 6,
        self::STATUS_NDR => 6,
        self::STATUS_DELIVERED => 7,
        self::STATUS_RTO_INITIATED => 8,
        self::STATUS_RTO_IN_TRANSIT => 9,
        self::STATUS_RTO_DELIVERED => 10,
        self::STATUS_CANCELLED => 11,
        self::STATUS_LOST => 11,
    ];

    public const STATUSES = [
        self::STATUS_CREATED,
        self::STATUS_AWB_ASSIGNED,
        self::STATUS_LABEL_GENERATED,
        self::STATUS_PICKUP_SCHEDULED,
        self::STATUS_PICKED_UP,
        self::STATUS_IN_TRANSIT,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_DELIVERED,
        self::STATUS_NDR,
        self::STATUS_RTO_INITIATED,
        self::STATUS_RTO_IN_TRANSIT,
        self::STATUS_RTO_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_LOST,
    ];

    /**
     * Nothing more will happen to the package.
     */
    public const FINAL_STATUSES = [self::STATUS_DELIVERED, self::STATUS_RTO_DELIVERED, self::STATUS_CANCELLED, self::STATUS_LOST];

    /**
     * Booked with the courier but not yet handed over.
     */
    public const PRE_PICKUP_STATUSES = [self::STATUS_CREATED, self::STATUS_AWB_ASSIGNED, self::STATUS_LABEL_GENERATED, self::STATUS_PICKUP_SCHEDULED];

    /**
     * Going back to the seller.
     */
    public const RTO_STATUSES = [self::STATUS_RTO_INITIATED, self::STATUS_RTO_IN_TRANSIT, self::STATUS_RTO_DELIVERED];

    protected $table = 'shipment';

    protected $fillable = [
        'seller_order_id',
        'direction',
        'return_request_id',
        'courier_id',
        'courier_rule_id',
        'pickup_batch_id',
        'strategy',
        'carrier_id',
        'carrier_name',
        'provider_order_id',
        'provider_shipment_id',
        'awb',
        'status',
        'payment_mode',
        'cod_amount',
        'weight_grams',
        'length_cm',
        'breadth_cm',
        'height_cm',
        'charged_weight_grams',
        'freight_charge',
        'cod_charge',
        'rto_charge',
        'label_url',
        'manifest_url',
        'invoice_url',
        'pickup_location_code',
        'pickup_address',
        'pickup_scheduled_at',
        'picked_up_at',
        'delivered_at',
        'rto_delivered_at',
        'edd',
        'tracking_url',
        'attempts',
        'needs_attention',
        'attention_reason',
        'last_status_label',
        'last_event_at',
        'last_synced_at',
        'rto_restocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_order_id' => 'integer',
            'return_request_id' => 'integer',
            'courier_id' => 'integer',
            'courier_rule_id' => 'integer',
            'pickup_batch_id' => 'integer',
            'cod_amount' => MoneyCast::class,
            'weight_grams' => 'integer',
            'charged_weight_grams' => 'integer',
            'freight_charge' => MoneyCast::class,
            'cod_charge' => MoneyCast::class,
            'rto_charge' => MoneyCast::class,
            'pickup_scheduled_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
            'rto_delivered_at' => 'datetime',
            'edd' => 'date',
            'attempts' => 'integer',
            'needs_attention' => 'integer',
            'last_event_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'rto_restocked' => 'integer',
            'pickup_address' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<Courier, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    /**
     * @return BelongsTo<CourierRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CourierRule::class, 'courier_rule_id');
    }

    /**
     * @return BelongsTo<PickupBatch, $this>
     */
    public function pickupBatch(): BelongsTo
    {
        return $this->belongsTo(PickupBatch::class, 'pickup_batch_id');
    }

    /**
     * @return HasMany<ShipmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class, 'shipment_id');
    }

    /**
     * Scans, oldest first.
     *
     * @return HasMany<ShipmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class, 'shipment_id')->orderBy('occurred_at')->orderBy('id');
    }

    /**
     * @return HasMany<NdrCase, $this>
     */
    public function ndrCases(): HasMany
    {
        return $this->hasMany(NdrCase::class, 'shipment_id')->orderByDesc('id');
    }

    /**
     * @param  Builder<Shipment>  $query
     */
    public function scopeForward(Builder $query): void
    {
        $query->where($this->qualifyColumn('direction'), self::DIRECTION_FORWARD);
    }

    /**
     * Still moving (handed over or booked) and not in a final state.
     *
     * @param  Builder<Shipment>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn($this->qualifyColumn('status'), self::FINAL_STATUSES);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function isPrePickup(): bool
    {
        return in_array($this->status, self::PRE_PICKUP_STATUSES, true);
    }

    public function isRto(): bool
    {
        return in_array($this->status, self::RTO_STATUSES, true);
    }

    public function isCod(): bool
    {
        return $this->payment_mode === self::PAYMENT_COD;
    }

    public function isBooked(): bool
    {
        return $this->awb !== null && $this->awb !== '';
    }

    public function needsAttention(): bool
    {
        return $this->needs_attention === (int) config('core.yes');
    }

    /**
     * Whether a scan mapped to $to may move the shipment (plan M13 §5.4): forward only, a
     * failed attempt (ndr) and a re-attempt (out_for_delivery) may alternate, the RTO branch
     * never returns to the forward path, cancellation only before pickup, and final states
     * stay final.
     */
    public function canAdvanceTo(string $to): bool
    {
        $from = $this->status;

        if ($to === $from || ! isset(self::RANKS[$to]) || $this->isFinal()) {
            return false;
        }

        if ($to === self::STATUS_CANCELLED) {
            return $this->isPrePickup();
        }

        if ($to === self::STATUS_LOST) {
            return true;
        }

        if ($to === self::STATUS_OUT_FOR_DELIVERY && $from === self::STATUS_NDR) {
            return true;
        }

        if ($to === self::STATUS_NDR) {
            return in_array($from, [self::STATUS_PICKED_UP, self::STATUS_IN_TRANSIT, self::STATUS_OUT_FOR_DELIVERY], true);
        }

        if ($this->isRto() && ! in_array($to, self::RTO_STATUSES, true)) {
            return false;
        }

        return self::RANKS[$to] > self::RANKS[$from];
    }

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }
}
