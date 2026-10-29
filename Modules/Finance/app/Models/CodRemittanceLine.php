<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Logistics\Models\Shipment;

/**
 * One AWB paid by a COD remittance and how it matched.
 *
 * @property int $id
 * @property int $cod_remittance_id
 * @property string $awb
 * @property int|null $shipment_id
 * @property Money $amount
 * @property Money|null $expected_amount
 * @property int $matched
 * @property string|null $issue
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CodRemittanceLine extends MarketplaceModel
{
    public const ISSUE_UNKNOWN_AWB = 'unknown_awb';

    public const ISSUE_AMOUNT_MISMATCH = 'amount_mismatch';

    public const ISSUE_NOT_COD = 'not_cod';

    public const ISSUE_NOT_DELIVERED = 'not_delivered';

    public const ISSUE_DUPLICATE = 'duplicate';

    protected $table = 'cod_remittance_line';

    protected $fillable = [
        'cod_remittance_id',
        'awb',
        'shipment_id',
        'amount',
        'expected_amount',
        'matched',
        'issue',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cod_remittance_id' => 'integer',
            'shipment_id' => 'integer',
            'amount' => MoneyCast::class,
            'expected_amount' => MoneyCast::class,
            'matched' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CodRemittance, $this>
     */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class, 'cod_remittance_id');
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function isMatched(): bool
    {
        return $this->matched === (int) config('core.yes');
    }
}
