<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerBankAccount;

/**
 * One seller's payout in a batch (SRS §7.6): scheduled → processing → paid | failed;
 * failed → processing (retry); paid → reversed (bank bounced it). Change it only through
 * PayoutService.
 *
 * @property int $id
 * @property int $payout_batch_id
 * @property string $payout_no
 * @property int $seller_id
 * @property int|null $bank_account_id
 * @property Money $amount
 * @property string $status
 * @property string $gateway
 * @property string|null $gateway_payout_id
 * @property string|null $utr
 * @property string|null $failure_reason
 * @property string $idempotency_key
 * @property list<int>|null $entry_ids
 * @property int $attempts
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SellerPayout extends MarketplaceModel
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [self::STATUS_SCHEDULED, self::STATUS_ON_HOLD, self::STATUS_PROCESSING, self::STATUS_PAID, self::STATUS_FAILED, self::STATUS_REVERSED];

    /**
     * Money has not reached the seller and the payout may still go out.
     */
    public const OPEN_STATUSES = [self::STATUS_SCHEDULED, self::STATUS_ON_HOLD, self::STATUS_PROCESSING];

    protected $table = 'seller_payout';

    protected $fillable = [
        'payout_batch_id',
        'payout_no',
        'seller_id',
        'bank_account_id',
        'amount',
        'status',
        'gateway',
        'gateway_payout_id',
        'utr',
        'failure_reason',
        'idempotency_key',
        'entry_ids',
        'attempts',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payout_batch_id' => 'integer',
            'seller_id' => 'integer',
            'bank_account_id' => 'integer',
            'amount' => MoneyCast::class,
            'entry_ids' => 'array',
            'attempts' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayoutBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayoutBatch::class, 'payout_batch_id');
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<SellerBankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(SellerBankAccount::class, 'bank_account_id');
    }

    /**
     * @return HasMany<SellerLedgerEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(SellerLedgerEntry::class, 'payout_id')->orderBy('id');
    }

    /**
     * @param  Builder<SellerPayout>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
