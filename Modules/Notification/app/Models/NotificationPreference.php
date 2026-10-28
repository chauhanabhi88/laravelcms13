<?php

declare(strict_types=1);

namespace Modules\Notification\Models;

use Illuminate\Support\Carbon;
use Modules\Core\Models\MarketplaceModel;

/**
 * A recipient's choice for one channel and opt-out category (FR-NTF-003). No row = enabled.
 *
 * @property int $id
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property string $channel
 * @property string $category
 * @property int $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class NotificationPreference extends MarketplaceModel
{
    protected $table = 'notification_preference';

    protected $fillable = ['notifiable_type', 'notifiable_id', 'channel', 'category', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifiable_id' => 'integer',
            'enabled' => 'integer',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled === (int) config('core.yes');
    }
}
