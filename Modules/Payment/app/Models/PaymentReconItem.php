<?php

declare(strict_types=1);

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Payment\Database\Factories\PaymentReconItemFactory;

/**
 * A settled gateway entity that does not match our records (FR-PAY-008).
 *
 * @property int $id
 * @property Carbon $settlement_date
 * @property string $entity_type
 * @property string $entity_id
 * @property string|null $settlement_id
 * @property string $issue
 * @property Money $gateway_amount
 * @property Money|null $local_amount
 * @property string|null $note
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentReconItem extends MarketplaceModel
{
    /** @use HasFactory<PaymentReconItemFactory> */
    use HasFactory;

    public const ISSUE_MISSING_LOCAL = 'missing_local';

    public const ISSUE_AMOUNT_MISMATCH = 'amount_mismatch';

    public const ISSUE_STATUS_MISMATCH = 'status_mismatch';

    public const ISSUES = [self::ISSUE_MISSING_LOCAL, self::ISSUE_AMOUNT_MISMATCH, self::ISSUE_STATUS_MISMATCH];

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    protected $table = 'payment_recon_item';

    protected $fillable = [
        'settlement_date',
        'entity_type',
        'entity_id',
        'settlement_id',
        'issue',
        'gateway_amount',
        'local_amount',
        'note',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'gateway_amount' => MoneyCast::class,
            'local_amount' => MoneyCast::class,
            'resolved_at' => 'datetime',
            'resolved_by' => 'integer',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    protected static function newFactory(): PaymentReconItemFactory
    {
        return PaymentReconItemFactory::new();
    }
}
