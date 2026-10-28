<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Modules\Notification\Services\InboxService;
use Modules\Theme\Contracts\NotificationBellProvider;

/**
 * The Theme bell for the signed-in customer (storefront header) or seller user (supplier
 * panel topbar). Hidden when nobody is signed in on that guard.
 */
class ThemeNotificationBell implements NotificationBellProvider
{
    public function __construct(private readonly InboxService $inbox) {}

    public function forCustomer(): ?array
    {
        return $this->bell(Auth::guard('customer')->user(), 'customer');
    }

    public function forSeller(): ?array
    {
        return config('auth.guards.seller') ? $this->bell(Auth::guard('seller')->user(), 'seller') : null;
    }

    /**
     * @return array{count: int, feed_url: string, index_url: string, read_url: string, read_all_url: string, poll_seconds: int}|null
     */
    private function bell(mixed $user, string $area): ?array
    {
        if (! $user instanceof Model || ! Route::has($area.'.notifications.feed')) {
            return null;
        }

        return [
            'count' => $this->inbox->unreadCount($user),
            'feed_url' => frontRoute($area.'.notifications.feed'),
            'index_url' => frontRoute($area.'.notifications.index'),
            'read_url' => frontRoute($area.'.notifications.read', ['__ID__']),
            'read_all_url' => frontRoute($area.'.notifications.read_all'),
            'poll_seconds' => (int) config('notification.bell.poll_seconds', 60),
        ];
    }
}
