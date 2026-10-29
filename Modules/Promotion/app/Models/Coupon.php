<?php

declare(strict_types=1);

namespace Modules\Promotion\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Customer\Models\Customer;
use Modules\Promotion\Database\Factories\CouponFactory;
use Modules\Seller\Models\Seller;

/**
 * A coupon (code) or an automatic offer (is_auto, plan M18 §3.1). Written through CouponService;
 * whether it applies to a cart is CouponValidator's decision.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property Money $value
 * @property Money|null $max_discount
 * @property Money $min_cart_value
 * @property string $scope
 * @property list<int>|null $scope_ids
 * @property string $funded_by
 * @property int|null $seller_id
 * @property int|null $customer_id
 * @property int|null $usage_limit_total
 * @property int|null $usage_limit_per_customer
 * @property int $used_count
 * @property int $first_order_only
 * @property int|null $new_customer_days
 * @property list<string>|null $channels
 * @property list<string>|null $payment_methods
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $is_auto
 * @property int $is_visible
 * @property int $stackable
 * @property int $status
 * @property string|null $batch
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Coupon extends MarketplaceModel
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FLAT = 'flat';

    public const TYPE_FREE_DELIVERY = 'free_delivery';

    public const SCOPE_ALL = 'all';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_SELLER = 'seller';

    public const SCOPE_PRODUCT = 'product';

    public const SCOPE_BRAND = 'brand';

    public const FUNDED_BY_PLATFORM = 'platform';

    public const FUNDED_BY_SELLER = 'seller';

    public const CHANNEL_WEB = 'web';

    public const CHANNEL_APP = 'app';

    protected $table = 'coupon';

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount',
        'min_cart_value',
        'scope',
        'scope_ids',
        'funded_by',
        'seller_id',
        'customer_id',
        'usage_limit_total',
        'usage_limit_per_customer',
        'first_order_only',
        'new_customer_days',
        'channels',
        'payment_methods',
        'starts_at',
        'ends_at',
        'is_auto',
        'is_visible',
        'stackable',
        'status',
        'batch',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => MoneyCast::class,
            'max_discount' => MoneyCast::class,
            'min_cart_value' => MoneyCast::class,
            'scope_ids' => 'array',
            'seller_id' => 'integer',
            'customer_id' => 'integer',
            'usage_limit_total' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'used_count' => 'integer',
            'first_order_only' => 'integer',
            'new_customer_days' => 'integer',
            'channels' => 'array',
            'payment_methods' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_auto' => 'integer',
            'is_visible' => 'integer',
            'stackable' => 'integer',
            'status' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public static function normaliseCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return HasMany<CouponUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class, 'coupon_id');
    }

    /**
     * Enabled and inside the validity window at $at.
     *
     * @param  Builder<Coupon>  $query
     */
    public function scopeLive(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->where($this->qualifyColumn('status'), config('core.enabled'))
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('starts_at'))->orWhere($this->qualifyColumn('starts_at'), '<=', $at))
            ->where(fn (Builder $query) => $query->whereNull($this->qualifyColumn('ends_at'))->orWhere($this->qualifyColumn('ends_at'), '>', $at));
    }

    /**
     * @param  Builder<Coupon>  $query
     */
    public function scopeAutomatic(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_auto'), config('core.yes'));
    }

    /**
     * @param  Builder<Coupon>  $query
     */
    public function scopeWithCode(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_auto'), config('core.no'));
    }

    /**
     * @param  Builder<Coupon>  $query
     */
    public function scopeForSeller(Builder $query, int $sellerId): void
    {
        $query->where($this->qualifyColumn('seller_id'), $sellerId);
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    public function isAutomatic(): bool
    {
        return $this->is_auto === (int) config('core.yes');
    }

    public function isVisible(): bool
    {
        return $this->is_visible === (int) config('core.yes');
    }

    public function isStackable(): bool
    {
        return $this->stackable === (int) config('core.yes');
    }

    public function isFirstOrderOnly(): bool
    {
        return $this->first_order_only === (int) config('core.yes');
    }

    public function isSellerFunded(): bool
    {
        return $this->funded_by === self::FUNDED_BY_SELLER;
    }

    public function isFreeDelivery(): bool
    {
        return $this->type === self::TYPE_FREE_DELIVERY;
    }

    /**
     * @return list<int>
     */
    public function scopeIdList(): array
    {
        return array_values(array_unique(array_map('intval', (array) ($this->scope_ids ?? []))));
    }

    /**
     * "50% off up to ₹100", "₹50 off", "Free delivery" (lang promotion::front.benefit.*).
     */
    public function benefitText(): string
    {
        return match ($this->type) {
            self::TYPE_PERCENT => $this->max_discount !== null && $this->max_discount->isPositive()
                ? trans('promotion::front.benefit.percent_capped', ['percent' => self::percentText($this->value), 'cap' => $this->max_discount->format()])
                : trans('promotion::front.benefit.percent', ['percent' => self::percentText($this->value)]),
            self::TYPE_FLAT => trans('promotion::front.benefit.flat', ['amount' => $this->value->format()]),
            default => trans('promotion::front.benefit.free_delivery'),
        };
    }

    public static function percentText(Money $value): string
    {
        return rtrim(rtrim($value->toDecimal(), '0'), '.');
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }
}
