<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Logistics\Database\Factories\NdrCaseFactory;

/**
 * A failed delivery attempt waiting for an action (FR-LOG-008). One open case per shipment;
 * later attempts update it.
 *
 * @property int $id
 * @property int $shipment_id
 * @property string|null $reason
 * @property int $attempts
 * @property string|null $customer_action
 * @property array<string, mixed>|null $action_payload
 * @property string $status
 * @property string|null $actioned_by
 * @property Carbon|null $actioned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NdrCase extends MarketplaceModel
{
    /** @use HasFactory<NdrCaseFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_ACTIONED = 'actioned';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_ACTIONED, self::STATUS_CLOSED];

    public const ACTION_REATTEMPT = 'reattempt';

    public const ACTION_CHANGE_ADDRESS = 'change_address';

    public const ACTION_CANCEL = 'cancel';

    protected $table = 'ndr_case';

    protected $fillable = [
        'shipment_id',
        'reason',
        'attempts',
        'customer_action',
        'action_payload',
        'status',
        'actioned_by',
        'actioned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'attempts' => 'integer',
            'action_payload' => 'array',
            'actioned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * @param  Builder<NdrCase>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    protected static function newFactory(): NdrCaseFactory
    {
        return NdrCaseFactory::new();
    }
}
