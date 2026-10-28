<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Logistics\Database\Factories\ShipmentEventFactory;

/**
 * A courier scan. status is our mapped status, or null when the courier status is unknown.
 *
 * @property int $id
 * @property int $shipment_id
 * @property string|null $status
 * @property string $provider_status
 * @property string $provider_status_code
 * @property string|null $location
 * @property string|null $remarks
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 */
class ShipmentEvent extends MarketplaceModel
{
    /** @use HasFactory<ShipmentEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'shipment_event';

    protected $fillable = [
        'shipment_id',
        'status',
        'provider_status',
        'provider_status_code',
        'location',
        'remarks',
        'occurred_at',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipment_id' => 'integer',
            'occurred_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    protected static function newFactory(): ShipmentEventFactory
    {
        return ShipmentEventFactory::new();
    }
}
