<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * Delivery record of one hub notification on one channel (FR-NTF-004).
 *
 * @property int $id
 * @property string|null $notification_id
 * @property string $event_key
 * @property string $channel
 * @property string|null $notifiable_type
 * @property int|null $notifiable_id
 * @property string|null $destination
 * @property string $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property-read object|null $notifiable
 */
class NotificationLog extends MarketplaceModel
{
    public const UPDATED_AT = null;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [self::STATUS_QUEUED, self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_SKIPPED];

    protected $table = 'notification_log';

    protected $fillable = [
        'notification_id',
        'event_key',
        'channel',
        'notifiable_type',
        'notifiable_id',
        'destination',
        'status',
        'provider_message_id',
        'error',
        'payload',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
            'payload' => 'array',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Failed and skipped deliveries can be sent again from the admin log.
     */
    public function isRetryable(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_SKIPPED], true)
            && $this->notifiable_type !== null
            && $this->notifiable_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return (array) ($this->payload['data'] ?? []);
    }
}
