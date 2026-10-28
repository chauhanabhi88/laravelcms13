<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;
use Modules\Notification\Database\Factories\NotificationTemplateFactory;

/**
 * SMS / push / in-app / WhatsApp text for one catalogue event, channel and audience.
 * Placeholders are {{name}}; the body of an SMS must match its DLT-registered text.
 *
 * @property int $id
 * @property string $event_key
 * @property string $channel
 * @property string $audience
 * @property string|null $title
 * @property string $body
 * @property string|null $provider_template_id
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationTemplate extends MarketplaceModel
{
    /** @use HasFactory<NotificationTemplateFactory> */
    use HasFactory;

    /**
     * Channels that use this table; email templates live in the Mail module.
     */
    public const CHANNELS = ['sms', 'push', 'database', 'whatsapp'];

    protected $table = 'notification_template';

    protected $fillable = ['event_key', 'channel', 'audience', 'title', 'body', 'provider_template_id', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => 'integer'];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', (int) config('core.enabled'));
    }

    public static function findActive(string $eventKey, string $channel, string $audience): ?self
    {
        return static::query()->active()
            ->where('event_key', $eventKey)
            ->where('channel', $channel)
            ->where('audience', $audience)
            ->first();
    }

    public function isEnabled(): bool
    {
        return $this->status === (int) config('core.enabled');
    }

    protected static function newFactory(): NotificationTemplateFactory
    {
        return NotificationTemplateFactory::new();
    }
}
