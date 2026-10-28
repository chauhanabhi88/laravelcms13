<?php

declare(strict_types=1);

namespace Modules\Logistics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Logistics\Database\Factories\CourierFactory;

/**
 * A courier account (FR-LOG-002). With an aggregator driver (shiprocket) the actual carrier
 * is chosen per shipment; credentials are encrypted at rest and fall back to .env.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $driver
 * @property array<string, mixed>|null $credentials
 * @property int $supports_cod
 * @property int $supports_reverse
 * @property int $priority
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Courier extends MarketplaceModel
{
    /** @use HasFactory<CourierFactory> */
    use HasFactory;

    public const DRIVER_SHIPROCKET = 'shiprocket';

    public const DRIVER_MANUAL = 'manual';

    public const DRIVER_FAKE = 'fake';

    protected $table = 'courier';

    protected $fillable = [
        'code',
        'name',
        'driver',
        'credentials',
        'supports_cod',
        'supports_reverse',
        'priority',
        'status',
    ];

    protected $hidden = ['credentials'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'supports_cod' => 'integer',
            'supports_reverse' => 'integer',
            'priority' => 'integer',
            'status' => 'integer',
        ];
    }

    /**
     * @param  Builder<Courier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), config('core.enabled'));
    }

    public function isActive(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    public function supportsCod(): bool
    {
        return $this->supports_cod === (int) config('core.yes');
    }

    public function supportsReverse(): bool
    {
        return $this->supports_reverse === (int) config('core.yes');
    }

    public function isManual(): bool
    {
        return $this->driver === self::DRIVER_MANUAL;
    }

    /**
     * One credential value (blank strings count as missing).
     */
    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    protected static function newFactory(): CourierFactory
    {
        return CourierFactory::new();
    }
}
