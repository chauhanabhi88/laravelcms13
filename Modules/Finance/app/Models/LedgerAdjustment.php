<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Seller\Models\Seller;
use Modules\User\Models\User;

/**
 * A manual ledger adjustment requested by one finance admin and approved by another
 * (maker-checker). Approval posts the "adjustment" ledger entry.
 *
 * @property int $id
 * @property int $seller_id
 * @property int|null $seller_order_id
 * @property string $direction
 * @property Money $amount
 * @property string $reason
 * @property string $status
 * @property int $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property int|null $ledger_entry_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LedgerAdjustment extends MarketplaceModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'seller_ledger_adjustment';

    protected $fillable = [
        'seller_id',
        'seller_order_id',
        'direction',
        'amount',
        'reason',
        'status',
        'requested_by',
        'decided_by',
        'decided_at',
        'ledger_entry_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_id' => 'integer',
            'seller_order_id' => 'integer',
            'amount' => MoneyCast::class,
            'requested_by' => 'integer',
            'decided_by' => 'integer',
            'decided_at' => 'datetime',
            'ledger_entry_id' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
