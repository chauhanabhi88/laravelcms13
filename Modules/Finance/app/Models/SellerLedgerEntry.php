<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Order\Models\SalesOrderItem;
use Modules\Order\Models\SellerOrder;
use Modules\Seller\Models\Seller;

/**
 * One append-only seller ledger entry (FR-FIN-002). Write only through LedgerPosterImpl and
 * PayoutService. What the entry is (seller, type, direction, amounts, links, key) never
 * changes: a correction is a new reversing entry. Only status and payout_id move.
 *
 * value() = amount + tax_amount: a commission debit also takes the GST on it.
 *
 * @property int $id
 * @property int $seller_id
 * @property int|null $seller_order_id
 * @property int|null $sales_order_item_id
 * @property int|null $return_request_id
 * @property int|null $payout_id
 * @property string $type
 * @property string $direction
 * @property Money $amount
 * @property Money $tax_amount
 * @property Money|null $base_amount
 * @property string $description
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $eligible_on
 * @property string $status
 * @property Carbon $posted_at
 * @property string $idempotency_key
 * @property string $created_by_type
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 */
class SellerLedgerEntry extends MarketplaceModel
{
    public const UPDATED_AT = null;

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ELIGIBLE = 'eligible';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_SETTLED = 'settled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_ELIGIBLE, self::STATUS_ON_HOLD, self::STATUS_SETTLED];

    public const TYPE_SALE = 'sale';

    public const TYPE_COMMISSION = 'commission';

    public const TYPE_COMMISSION_REVERSAL = 'commission_reversal';

    public const TYPE_PLATFORM_FEE = 'platform_fee';

    public const TYPE_PLATFORM_FEE_REVERSAL = 'platform_fee_reversal';

    public const TYPE_LOGISTICS_FORWARD = 'logistics_forward';

    public const TYPE_LOGISTICS_RTO = 'logistics_rto';

    public const TYPE_LOGISTICS_REVERSE = 'logistics_reverse';

    public const TYPE_RETURN_REVERSAL = 'return_reversal';

    public const TYPE_PENALTY = 'penalty';

    public const TYPE_CLAIM_CREDIT = 'claim_credit';

    public const TYPE_TCS = 'tcs';

    public const TYPE_TDS = 'tds';

    public const TYPE_TCS_REVERSAL = 'tcs_reversal';

    public const TYPE_TDS_REVERSAL = 'tds_reversal';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_PAYOUT = 'payout';

    public const TYPES = [
        self::TYPE_SALE,
        self::TYPE_COMMISSION,
        self::TYPE_COMMISSION_REVERSAL,
        self::TYPE_PLATFORM_FEE,
        self::TYPE_PLATFORM_FEE_REVERSAL,
        self::TYPE_LOGISTICS_FORWARD,
        self::TYPE_LOGISTICS_RTO,
        self::TYPE_LOGISTICS_REVERSE,
        self::TYPE_RETURN_REVERSAL,
        self::TYPE_PENALTY,
        self::TYPE_CLAIM_CREDIT,
        self::TYPE_TCS,
        self::TYPE_TDS,
        self::TYPE_TCS_REVERSAL,
        self::TYPE_TDS_REVERSAL,
        self::TYPE_ADJUSTMENT,
        self::TYPE_PAYOUT,
    ];

    /**
     * Columns that define the entry; changing one after insert throws.
     */
    public const IMMUTABLE = [
        'seller_id',
        'seller_order_id',
        'sales_order_item_id',
        'return_request_id',
        'type',
        'direction',
        'amount',
        'tax_amount',
        'base_amount',
        'description',
        'eligible_on',
        'posted_at',
        'idempotency_key',
        'created_by_type',
        'created_by_id',
    ];

    protected $table = 'seller_ledger_entry';

    protected $fillable = [
        'seller_id',
        'seller_order_id',
        'sales_order_item_id',
        'return_request_id',
        'payout_id',
        'type',
        'direction',
        'amount',
        'tax_amount',
        'base_amount',
        'description',
        'meta',
        'eligible_on',
        'status',
        'posted_at',
        'idempotency_key',
        'created_by_type',
        'created_by_id',
    ];

    protected static function booted(): void
    {
        static::updating(function (SellerLedgerEntry $entry): void {
            if ($entry->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Seller ledger entries are append-only: post a reversing entry instead of editing '.implode(', ', array_keys($entry->getDirty())).'.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Seller ledger entries are append-only and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'seller_order_id' => 'integer',
            'sales_order_item_id' => 'integer',
            'return_request_id' => 'integer',
            'payout_id' => 'integer',
            'amount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'base_amount' => MoneyCast::class,
            'meta' => 'array',
            'eligible_on' => 'date',
            'posted_at' => 'datetime',
            'created_by_id' => 'integer',
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
     * @return BelongsTo<SellerOrder, $this>
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(SellerOrder::class, 'seller_order_id');
    }

    /**
     * @return BelongsTo<SalesOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    /**
     * @return BelongsTo<SellerPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(SellerPayout::class, 'payout_id');
    }

    /**
     * @param  Builder<SellerLedgerEntry>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    public function isCredit(): bool
    {
        return $this->direction === self::CREDIT;
    }

    /**
     * Amount plus the GST on it.
     */
    public function value(): Money
    {
        return $this->amount->plus($this->tax_amount);
    }

    /**
     * + for a credit to the seller, − for a debit.
     */
    public function signedValue(): Money
    {
        return $this->isCredit() ? $this->value() : Money::zero()->minus($this->value());
    }

    /**
     * SQL for the signed value (credits − debits), for SUM() aggregates.
     */
    public static function signedSql(string $table = 'seller_ledger_entry'): string
    {
        return "CASE WHEN {$table}.direction = '".self::CREDIT."' THEN {$table}.amount + {$table}.tax_amount ELSE -({$table}.amount + {$table}.tax_amount) END";
    }
}
