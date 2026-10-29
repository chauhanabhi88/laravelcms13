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

/**
 * One reseller wallet entry (FR-FIN-007), append-only like the seller ledger: margin credits
 * wait (pending) until the return window closes, then become available; a withdrawal is a
 * debit that turns "paid" once the payout lands. Write through ResellerWalletService.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $type
 * @property string $direction
 * @property Money $amount
 * @property int|null $sales_order_item_id
 * @property int|null $withdrawal_id
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $eligible_on
 * @property string $idempotency_key
 * @property Carbon|null $created_at
 */
class ResellerWalletEntry extends MarketplaceModel
{
    public const UPDATED_AT = null;

    public const TYPE_MARGIN_CREDIT = 'margin_credit';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_REVERSAL = 'reversal';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PAID = 'paid';

    public const IMMUTABLE = ['customer_id', 'type', 'direction', 'amount', 'sales_order_item_id', 'withdrawal_id', 'eligible_on', 'idempotency_key'];

    protected $table = 'reseller_wallet_entry';

    protected $fillable = [
        'customer_id',
        'type',
        'direction',
        'amount',
        'sales_order_item_id',
        'withdrawal_id',
        'description',
        'status',
        'eligible_on',
        'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::updating(function (ResellerWalletEntry $entry): void {
            if ($entry->isDirty(self::IMMUTABLE)) {
                throw new LogicException('Reseller wallet entries are append-only.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Reseller wallet entries cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'amount' => MoneyCast::class,
            'sales_order_item_id' => 'integer',
            'withdrawal_id' => 'integer',
            'eligible_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<SalesOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    /**
     * @param  Builder<ResellerWalletEntry>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where($this->qualifyColumn('customer_id'), $customerId);
    }

    public function isCredit(): bool
    {
        return $this->direction === self::CREDIT;
    }

    public function signedAmount(): Money
    {
        return $this->isCredit() ? $this->amount : Money::zero()->minus($this->amount);
    }
}
