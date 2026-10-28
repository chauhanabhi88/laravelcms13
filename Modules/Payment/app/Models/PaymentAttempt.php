<?php

declare(strict_types=1);

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Payment\Database\Factories\PaymentAttemptFactory;

/**
 * A gateway attempt on a payment (FR-PAY-005), written by PaymentService.
 *
 * @property int $id
 * @property int $payment_id
 * @property string|null $gateway_payment_id
 * @property string $status
 * @property string $source
 * @property string|null $error_code
 * @property string|null $error_description
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $created_at
 */
class PaymentAttempt extends MarketplaceModel
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUS_INVALID_SIGNATURE = 'invalid_signature';

    public const SOURCE_CALLBACK = 'callback';

    public const SOURCE_WEBHOOK = 'webhook';

    public const SOURCE_EXPIRY = 'expiry';

    protected $table = 'payment_attempt';

    protected $fillable = [
        'payment_id',
        'gateway_payment_id',
        'status',
        'source',
        'error_code',
        'error_description',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'raw' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    protected static function newFactory(): PaymentAttemptFactory
    {
        return PaymentAttemptFactory::new();
    }
}
