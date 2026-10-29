<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;

/**
 * A reseller's withdrawal of their available margin to a UPI id or bank account: pending →
 * processing → paid | failed. A failure credits the amount back to the wallet.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $withdrawal_no
 * @property Money $amount
 * @property string $mode
 * @property array<string, string> $beneficiary
 * @property string|null $beneficiary_label
 * @property string $status
 * @property string|null $gateway
 * @property string|null $gateway_payout_id
 * @property string|null $utr
 * @property string|null $failure_reason
 * @property string $idempotency_key
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ResellerWithdrawal extends MarketplaceModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_PAID, self::STATUS_FAILED];

    public const MODE_UPI = 'upi';

    public const MODE_BANK = 'bank';

    protected $table = 'reseller_withdrawal';

    protected $fillable = [
        'customer_id',
        'withdrawal_no',
        'amount',
        'mode',
        'beneficiary',
        'beneficiary_label',
        'status',
        'gateway',
        'gateway_payout_id',
        'utr',
        'failure_reason',
        'idempotency_key',
        'paid_at',
    ];

    protected $hidden = ['beneficiary'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'amount' => MoneyCast::class,
            'beneficiary' => 'encrypted:array',
            'paid_at' => 'datetime',
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
     * @param  Builder<ResellerWithdrawal>  $query
     */
    public function scopeForCustomer(Builder $query, int $customerId): void
    {
        $query->where($this->qualifyColumn('customer_id'), $customerId);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }
}
