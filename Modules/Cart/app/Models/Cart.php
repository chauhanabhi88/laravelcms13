<?php

declare(strict_types=1);

namespace Modules\Cart\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Cart\Database\Factories\CartFactory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Customer\Models\Customer;

/**
 * A guest (token) or customer cart. Lines change only through CartService.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $token
 * @property string $type
 * @property string|null $coupon_code
 * @property string|null $delivery_pincode
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $reminded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Cart extends MarketplaceModel
{
    /** @use HasFactory<CartFactory> */
    use HasFactory;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_BUY_NOW = 'buy_now';

    protected $table = 'cart';

    protected $fillable = [
        'customer_id',
        'token',
        'type',
        'coupon_code',
        'delivery_pincode',
        'last_activity_at',
        'reminded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'last_activity_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Every line, active and saved for later.
     *
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }

    /**
     * @param  Builder<Cart>  $query
     */
    public function scopeStandard(Builder $query): void
    {
        $query->where($this->qualifyColumn('type'), self::TYPE_STANDARD);
    }

    /**
     * @param  Builder<Cart>  $query
     */
    public function scopeBuyNow(Builder $query): void
    {
        $query->where($this->qualifyColumn('type'), self::TYPE_BUY_NOW);
    }

    public function isBuyNow(): bool
    {
        return $this->type === self::TYPE_BUY_NOW;
    }

    public function isGuest(): bool
    {
        return $this->customer_id === null;
    }

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
}
