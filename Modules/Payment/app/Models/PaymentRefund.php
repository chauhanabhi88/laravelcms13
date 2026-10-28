<?php

declare(strict_types=1);

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\MoneyCast;
use Modules\Core\Concerns\HasStateMachine;
use Modules\Core\Models\MarketplaceModel;
use Modules\Core\Support\Money;
use Modules\Payment\Database\Factories\PaymentRefundFactory;

/**
 * A refund against a payment (FR-PAY-006). Written by RefundService only.
 *
 * @property int $id
 * @property int $payment_id
 * @property int|null $return_request_id
 * @property int|null $sales_order_item_id
 * @property Money $amount
 * @property string $mode
 * @property string $status
 * @property string|null $gateway_refund_id
 * @property string|null $payout_id
 * @property string|null $utr
 * @property array<string, mixed>|null $beneficiary
 * @property string|null $reason
 * @property string $idempotency_key
 * @property Carbon|null $processed_at
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentRefund extends MarketplaceModel
{
    /** @use HasFactory<PaymentRefundFactory> */
    use HasFactory;

    use HasStateMachine;

    public const MODE_SOURCE = 'source';

    public const MODE_BANK = 'bank';

    public const MODE_UPI = 'upi';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /**
     * Refunds that hold part of the refundable amount (everything except failed ones).
     */
    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_PROCESSED];

    /**
     * @var array<string, list<string>>
     */
    protected static array $transitions = [
        self::STATUS_PENDING => [self::STATUS_PROCESSING, self::STATUS_PROCESSED, self::STATUS_FAILED],
        self::STATUS_PROCESSING => [self::STATUS_PROCESSED, self::STATUS_FAILED],
        // Retry of a failed refund.
        self::STATUS_FAILED => [self::STATUS_PENDING],
    ];

    protected $table = 'payment_refund';

    protected $fillable = [
        'payment_id',
        'return_request_id',
        'sales_order_item_id',
        'amount',
        'mode',
        'status',
        'gateway_refund_id',
        'payout_id',
        'utr',
        'beneficiary',
        'reason',
        'idempotency_key',
        'processed_at',
        'failure_reason',
    ];

    /**
     * Bank details never appear in arrays / JSON.
     *
     * @var list<string>
     */
    protected $hidden = ['beneficiary'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'return_request_id' => 'integer',
            'sales_order_item_id' => 'integer',
            'amount' => MoneyCast::class,
            'beneficiary' => 'encrypted:array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function isToSource(): bool
    {
        return $this->mode === self::MODE_SOURCE;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Masked beneficiary for screens: "XXXXXX1234 / HDFC0001234" or "name@upi".
     */
    public function maskedBeneficiary(): ?string
    {
        $beneficiary = (array) ($this->beneficiary ?? []);

        if (isset($beneficiary['vpa'])) {
            return (string) $beneficiary['vpa'];
        }

        if (isset($beneficiary['account_number'])) {
            $number = (string) $beneficiary['account_number'];

            return str_repeat('X', max(0, strlen($number) - 4)).substr($number, -4).(isset($beneficiary['ifsc']) ? ' / '.$beneficiary['ifsc'] : '');
        }

        return null;
    }

    protected static function newFactory(): PaymentRefundFactory
    {
        return PaymentRefundFactory::new();
    }
}
