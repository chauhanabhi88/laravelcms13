<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;

/**
 * A COD remittance from the courier aggregator: received → reconciled (every AWB matched a
 * delivered COD shipment for the right amount) | mismatch.
 *
 * @property int $id
 * @property string $provider
 * @property string $remittance_ref
 * @property Carbon $remittance_date
 * @property Money $amount
 * @property string|null $utr
 * @property string $status
 * @property int $lines_count
 * @property int $mismatch_count
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CodRemittance extends MarketplaceModel
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_RECONCILED = 'reconciled';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUSES = [self::STATUS_RECEIVED, self::STATUS_RECONCILED, self::STATUS_MISMATCH];

    protected $table = 'cod_remittance';

    protected $fillable = [
        'provider',
        'remittance_ref',
        'remittance_date',
        'amount',
        'utr',
        'status',
        'lines_count',
        'mismatch_count',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remittance_date' => 'date',
            'amount' => MoneyCast::class,
            'lines_count' => 'integer',
            'mismatch_count' => 'integer',
            'raw' => 'array',
        ];
    }

    /**
     * @return HasMany<CodRemittanceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CodRemittanceLine::class, 'cod_remittance_id')->orderBy('id');
    }
}
