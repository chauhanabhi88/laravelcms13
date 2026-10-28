<?php

declare(strict_types=1);

namespace Modules\Payment\Models;

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
use Modules\Payment\Database\Factories\PaymentFactory;

/**
 * One payment of a sales order (SRS §7.4). State changes go through PaymentService only.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property string|null $order_no
 * @property int $customer_id
 * @property string $method
 * @property string $status
 * @property Money $amount
 * @property Money $amount_refunded
 * @property string $currency
 * @property string|null $gateway_order_id
 * @property string|null $gateway_payment_id
 * @property string|null $instrument
 * @property array<string, mixed>|null $instrument_meta
 * @property Carbon|null $captured_at
 * @property Carbon|null $expires_at
 * @property string|null $failure_reason
 * @property int $cod_otp_required
 * @property Carbon|null $cod_otp_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends MarketplaceModel
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasStateMachine;

    public const METHOD_RAZORPAY = 'razorpay';

    public const METHOD_COD = 'cod';

    public const METHOD_FAKE = 'fake';

    public const STATUS_CREATED = 'created';

    public const STATUS_ATTEMPTED = 'attempted';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_REMITTED = 'remitted';

    /**
     * Prepaid payments still waiting for the buyer (retry window open).
     */
    public const OPEN_PREPAID_STATUSES = [self::STATUS_CREATED, self::STATUS_ATTEMPTED];

    /**
     * Money has been received (or, for COD, collected) and can be refunded.
     */
    public const REFUNDABLE_STATUSES = [
        self::STATUS_CAPTURED,
        self::STATUS_PARTIALLY_REFUNDED,
        self::STATUS_COLLECTED,
        self::STATUS_REMITTED,
    ];

    /**
     * @var array<string, list<string>>
     */
    protected static array $transitions = [
        self::STATUS_CREATED => [self::STATUS_ATTEMPTED, self::STATUS_CAPTURED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        self::STATUS_ATTEMPTED => [self::STATUS_CAPTURED, self::STATUS_FAILED, self::STATUS_CANCELLED],
        // A capture reported after the window closed (late webhook) still has to be honoured.
        self::STATUS_FAILED => [self::STATUS_CAPTURED],
        self::STATUS_CAPTURED => [self::STATUS_PARTIALLY_REFUNDED, self::STATUS_REFUNDED],
        self::STATUS_PARTIALLY_REFUNDED => [self::STATUS_REFUNDED],
        self::STATUS_PENDING => [self::STATUS_COLLECTED, self::STATUS_CANCELLED, self::STATUS_FAILED],
        self::STATUS_COLLECTED => [self::STATUS_REMITTED, self::STATUS_PARTIALLY_REFUNDED, self::STATUS_REFUNDED],
        self::STATUS_REMITTED => [self::STATUS_PARTIALLY_REFUNDED, self::STATUS_REFUNDED],
    ];

    protected $table = 'payment';

    protected $fillable = [
        'sales_order_id',
        'order_no',
        'customer_id',
        'method',
        'status',
        'amount',
        'amount_refunded',
        'currency',
        'gateway_order_id',
        'gateway_payment_id',
        'instrument',
        'instrument_meta',
        'captured_at',
        'expires_at',
        'failure_reason',
        'cod_otp_required',
        'cod_otp_verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sales_order_id' => 'integer',
            'customer_id' => 'integer',
            'amount' => MoneyCast::class,
            'amount_refunded' => MoneyCast::class,
            'instrument_meta' => 'array',
            'captured_at' => 'datetime',
            'expires_at' => 'datetime',
            'cod_otp_required' => 'integer',
            'cod_otp_verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return HasMany<PaymentAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class, 'payment_id');
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id');
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeForOrder(Builder $query, int $salesOrderId): void
    {
        $query->where($this->qualifyColumn('sales_order_id'), $salesOrderId);
    }

    /**
     * Payments that are neither finished nor abandoned: prepaid waiting for the buyer, or COD.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn($this->qualifyColumn('status'), [self::STATUS_CANCELLED, self::STATUS_FAILED]);
    }

    public function isCod(): bool
    {
        return $this->method === self::METHOD_COD;
    }

    public function isPrepaid(): bool
    {
        return ! $this->isCod();
    }

    /**
     * A prepaid payment the buyer can still complete.
     */
    public function isAwaitingPayment(): bool
    {
        return $this->isPrepaid() && in_array($this->status, self::OPEN_PREPAID_STATUSES, true);
    }

    public function isCaptured(): bool
    {
        return in_array($this->status, [self::STATUS_CAPTURED, self::STATUS_PARTIALLY_REFUNDED, self::STATUS_REFUNDED], true);
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, self::REFUNDABLE_STATUSES, true);
    }

    public function isWindowOpen(?Carbon $now = null): bool
    {
        return $this->expires_at === null || $this->expires_at->isAfter($now ?? Carbon::now());
    }

    public function requiresCodOtp(): bool
    {
        return $this->isCod() && $this->cod_otp_required === (int) config('core.yes');
    }

    /**
     * COD waiting for the buyer's OTP before the order may be confirmed.
     */
    public function awaitingCodOtp(): bool
    {
        return $this->requiresCodOtp() && $this->cod_otp_verified_at === null && $this->status === self::STATUS_PENDING;
    }

    /**
     * Amount in paise, as gateways expect it.
     */
    public function amountInPaise(): int
    {
        return $this->amount->paise;
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
