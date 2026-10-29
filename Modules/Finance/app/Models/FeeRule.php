<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Finance\Database\Factories\FeeRuleFactory;
use Modules\Seller\Models\Seller;

/**
 * A platform fee other than commission (plan M17 §3.2), scoped like a commission rule. Only
 * rules charged to the seller reach the ledger.
 *
 * Type "flat" is a rupee amount per item (closing fee) or per event (shipping, penalty);
 * "percent" is a percentage of the item price, or of the courier's actual charge for shipping.
 *
 * @property int $id
 * @property string $code
 * @property string $scope
 * @property int|null $catalog_category_id
 * @property int|null $seller_id
 * @property string $type
 * @property string $value
 * @property string $charged_to
 * @property int $status
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FeeRule extends MarketplaceModel
{
    /** @use HasFactory<FeeRuleFactory> */
    use HasFactory;

    public const CODE_FIXED_CLOSING_FEE = 'fixed_closing_fee';

    public const CODE_PAYMENT_GATEWAY_FEE = 'payment_gateway_fee';

    public const CODE_FORWARD_SHIPPING = 'forward_shipping';

    public const CODE_RTO_SHIPPING = 'rto_shipping';

    public const CODE_REVERSE_SHIPPING = 'reverse_shipping';

    public const CODE_SELLER_CANCEL_PENALTY = 'seller_cancel_penalty';

    public const CODE_SLA_BREACH_PENALTY = 'sla_breach_penalty';

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FLAT = 'flat';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FLAT];

    public const CHARGED_TO_SELLER = 'seller';

    public const CHARGED_TO_PLATFORM = 'platform';

    public const CHARGED_TO = [self::CHARGED_TO_SELLER, self::CHARGED_TO_PLATFORM];

    protected $table = 'fee_rule';

    protected $fillable = [
        'code',
        'scope',
        'catalog_category_id',
        'seller_id',
        'type',
        'value',
        'charged_to',
        'status',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'catalog_category_id' => 'integer',
            'seller_id' => 'integer',
            'value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'status' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CatalogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(CatalogCategory::class, 'catalog_category_id');
    }

    /**
     * @return BelongsTo<Seller, $this>
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }

    /**
     * @param  Builder<FeeRule>  $query
     */
    public function scopeInForce(Builder $query, Carbon $on): void
    {
        $query->where('status', config('core.enabled'))
            ->whereDate('effective_from', '<=', $on->toDateString())
            ->where(fn (Builder $to) => $to->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on->toDateString()));
    }

    public function isChargedToSeller(): bool
    {
        return $this->charged_to === self::CHARGED_TO_SELLER;
    }

    protected static function newFactory(): FeeRuleFactory
    {
        return FeeRuleFactory::new();
    }
}
