<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Catalog\Models\CatalogCategory;
use Modules\Core\Models\MarketplaceModel;
use Modules\Finance\Database\Factories\CommissionRuleFactory;
use Modules\Seller\Models\Seller;

/**
 * A commission rule (FR-FIN-001). Resolve through CommissionResolver; past orders keep their
 * snapshot, so editing a rule never changes them.
 *
 * @property int $id
 * @property string $scope
 * @property int|null $catalog_category_id
 * @property int|null $seller_id
 * @property string $type
 * @property string $value
 * @property string $applies_to
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CommissionRule extends MarketplaceModel
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_SELLER = 'seller';

    public const SCOPE_SELLER_CATEGORY = 'seller_category';

    public const SCOPES = [self::SCOPE_GLOBAL, self::SCOPE_CATEGORY, self::SCOPE_SELLER, self::SCOPE_SELLER_CATEGORY];

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FLAT_PER_ITEM = 'flat_per_item';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FLAT_PER_ITEM];

    public const APPLIES_TO_ITEM_PRICE = 'item_price';

    public const APPLIES_TO_TAXABLE_VALUE = 'taxable_value';

    public const APPLIES_TO = [self::APPLIES_TO_ITEM_PRICE, self::APPLIES_TO_TAXABLE_VALUE];

    protected $table = 'commission_rule';

    protected $fillable = [
        'scope',
        'catalog_category_id',
        'seller_id',
        'type',
        'value',
        'applies_to',
        'effective_from',
        'effective_to',
        'status',
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
     * Active and in force on the date.
     *
     * @param  Builder<CommissionRule>  $query
     */
    public function scopeInForce(Builder $query, Carbon $on): void
    {
        $query->where('status', config('core.enabled'))
            ->whereDate('effective_from', '<=', $on->toDateString())
            ->where(fn (Builder $to) => $to->whereNull('effective_to')->orWhereDate('effective_to', '>=', $on->toDateString()));
    }

    protected static function newFactory(): CommissionRuleFactory
    {
        return CommissionRuleFactory::new();
    }
}
