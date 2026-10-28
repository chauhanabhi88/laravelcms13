<?php

declare(strict_types=1);

namespace Modules\Theme\Support;

use Modules\Theme\Contracts\NotificationBellProvider;

/**
 * No bell until the Notification module (M09) binds its provider.
 */
class NullNotificationBellProvider implements NotificationBellProvider
{
    public function forCustomer(): ?array
    {
        return null;
    }

    public function forSeller(): ?array
    {
        return null;
    }
}
