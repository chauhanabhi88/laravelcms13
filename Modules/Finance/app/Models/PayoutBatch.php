<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Finance\Database\Factories\PayoutBatchFactory;
use Modules\User\Models\User;

/**
 * One settlement cycle (FR-FIN-004): draft → approved (a finance admin, not the maker) →
 * processing → completed | partially_failed. Change it only through PayoutService.
 *
 * @property int $id
 * @property string $batch_no
 * @property Carbon|null $cycle_start
 * @property Carbon $cycle_end
 * @property string $status
 * @property string $gateway
 * @property Money $total_amount
 * @property int $sellers_count
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PayoutBatch extends MarketplaceModel
{
    /** @use HasFactory<PayoutBatchFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIALLY_FAILED = 'partially_failed';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_PROCESSING, self::STATUS_COMPLETED, self::STATUS_PARTIALLY_FAILED];

    protected $table = 'payout_batch';

    protected $fillable = [
        'batch_no',
        'cycle_start',
        'cycle_end',
        'status',
        'gateway',
        'total_amount',
        'sellers_count',
        'created_by',
        'approved_by',
        'approved_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cycle_start' => 'date',
            'cycle_end' => 'date',
            'total_amount' => MoneyCast::class,
            'sellers_count' => 'integer',
            'created_by' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SellerPayout, $this>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(SellerPayout::class, 'payout_batch_id')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    protected static function newFactory(): PayoutBatchFactory
    {
        return PayoutBatchFactory::new();
    }
}
