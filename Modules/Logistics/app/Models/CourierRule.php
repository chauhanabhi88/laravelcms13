<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Logistics\Database\Factories\CourierRuleFactory;

/**
 * A courier assignment rule (FR-LOG-003, plan M13 §3.2). Every condition present must match;
 * an absent or empty condition matches anything.
 *
 * @property int $id
 * @property string $name
 * @property int $priority
 * @property array{zones?: list<string>, state_ids?: list<int>, pincode_prefixes?: list<string>, weight_min_g?: int|null, weight_max_g?: int|null, payment?: list<string>, seller_ids?: list<int>, category_ids?: list<int>}|null $conditions
 * @property int $courier_id
 * @property list<string>|null $preferred_carrier_ids
 * @property string $strategy
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourierRule extends MarketplaceModel
{
    /** @use HasFactory<CourierRuleFactory> */
    use HasFactory;

    public const STRATEGY_RECOMMENDED = 'recommended';

    public const STRATEGY_CHEAPEST = 'cheapest';

    public const STRATEGY_FASTEST = 'fastest';

    public const STRATEGY_PREFERRED_LIST = 'preferred_list';

    protected $table = 'courier_rule';

    protected $fillable = [
        'name',
        'priority',
        'conditions',
        'courier_id',
        'preferred_carrier_ids',
        'strategy',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'conditions' => 'array',
            'courier_id' => 'integer',
            'preferred_carrier_ids' => 'array',
            'status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Courier, $this>
     */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }

    /**
     * Active rules in evaluation order.
     *
     * @param  Builder<CourierRule>  $query
     */
    public function scopeEvaluationOrder(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), config('core.enabled'))
            ->orderBy($this->qualifyColumn('priority'))
            ->orderBy($this->qualifyColumn('id'));
    }

    /**
     * @return list<string>
     */
    public function preferredCarrierIds(): array
    {
        return array_values(array_map('strval', (array) ($this->preferred_carrier_ids ?? [])));
    }

    protected static function newFactory(): CourierRuleFactory
    {
        return CourierRuleFactory::new();
    }
}
