<?php

declare(strict_types=1);

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Payment\Database\Factories\WebhookEventFactory;

/**
 * An inbound provider webhook (FR-PAY-003). The unique (provider, event_id) key makes
 * redelivery a no-op; ProcessWebhookEvent does the work.
 *
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string|null $event_type
 * @property int $signature_valid
 * @property array<string, mixed>|null $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $error
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 */
class WebhookEvent extends MarketplaceModel
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const PROVIDER_RAZORPAY = 'razorpay';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUSES = [self::STATUS_RECEIVED, self::STATUS_PROCESSED, self::STATUS_FAILED, self::STATUS_IGNORED];

    protected $table = 'webhook_event';

    protected $fillable = [
        'provider',
        'event_id',
        'event_type',
        'signature_valid',
        'payload',
        'status',
        'attempts',
        'error',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'integer',
            'payload' => 'array',
            'attempts' => 'integer',
            'processed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function hasValidSignature(): bool
    {
        return $this->signature_valid === (int) config('core.yes');
    }

    /**
     * Only signed events can be (re)processed.
     */
    public function isReplayable(): bool
    {
        return $this->hasValidSignature() && $this->provider === self::PROVIDER_RAZORPAY;
    }

    protected static function newFactory(): WebhookEventFactory
    {
        return WebhookEventFactory::new();
    }
}
