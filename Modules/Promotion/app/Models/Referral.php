<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;

/**
 * A friend who signed up through a referral code (plan M18 §3.4): pending until the friend's
 * first delivered order qualifies it, rewarded once the referrer's coupon is issued.
 *
 * @property int $id
 * @property int $referrer_customer_id
 * @property int $referred_customer_id
 * @property string $code
 * @property string $status
 * @property Money $reward_amount
 * @property int|null $reward_coupon_id
 * @property int|null $qualified_order_id
 * @property Carbon|null $qualified_at
 * @property Carbon|null $rewarded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Referral extends MarketplaceModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_REWARDED = 'rewarded';

    protected $table = 'referral';

    protected $fillable = [
        'referrer_customer_id',
        'referred_customer_id',
        'code',
        'status',
        'reward_amount',
        'reward_coupon_id',
        'qualified_order_id',
        'qualified_at',
        'rewarded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'referrer_customer_id' => 'integer',
            'referred_customer_id' => 'integer',
            'reward_amount' => MoneyCast::class,
            'reward_coupon_id' => 'integer',
            'qualified_order_id' => 'integer',
            'qualified_at' => 'datetime',
            'rewarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function rewardCoupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'reward_coupon_id');
    }
}
