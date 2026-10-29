<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Order\Models\SalesOrder;

/**
 * A coupon (or automatic offer) used by an order (plan M18 §3.2). Written by
 * PromotionOrderHooks at placement and reversed when the whole order is cancelled.
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $customer_id
 * @property int $sales_order_id
 * @property string $code
 * @property Money $discount_amount
 * @property string $status
 * @property Carbon|null $reversed_at
 * @property Carbon|null $created_at
 */
class CouponUsage extends MarketplaceModel
{
    public const STATUS_APPLIED = 'applied';

    public const STATUS_REVERSED = 'reversed';

    public const UPDATED_AT = null;

    protected $table = 'coupon_usage';

    protected $fillable = [
        'coupon_id',
        'customer_id',
        'sales_order_id',
        'code',
        'discount_amount',
        'status',
        'reversed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'coupon_id' => 'integer',
            'customer_id' => 'integer',
            'sales_order_id' => 'integer',
            'discount_amount' => MoneyCast::class,
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /**
     * @param  Builder<CouponUsage>  $query
     */
    public function scopeApplied(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), self::STATUS_APPLIED);
    }
}
