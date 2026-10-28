<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The in-app inbox of a customer or seller user (FR-NTF-005): unread count (cached), the
 * latest entries for the bell, the paginated list, and marking as read.
 */
class InboxService
{
    public function unreadCount(Model $notifiable): int
    {
        return (int) Cache::remember(
            $this->cacheKey($notifiable),
            max(1, (int) config('notification.bell.cache_seconds', 60)),
            fn (): int => $notifiable->unreadNotifications()->count(),
        );
    }

    public function forgetUnreadCount(Model $notifiable): void
    {
        Cache::forget($this->cacheKey($notifiable));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(Model $notifiable, ?int $limit = null): array
    {
        return $notifiable->notifications()
            ->latest()
            ->limit($limit ?? (int) config('notification.bell.dropdown_limit', 10))
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->present($notification))
            ->values()
            ->all();
    }

    public function paginate(Model $notifiable, ?int $perPage = null, int $page = 1): LengthAwarePaginator
    {
        return $notifiable->notifications()
            ->latest()
            ->paginate($perPage ?? (int) config('notification.bell.per_page', 20), ['*'], 'page', max(1, $page));
    }

    /**
     * Mark one of the notifiable's own entries as read; null when it is not theirs.
     */
    public function markRead(Model $notifiable, string $id): ?DatabaseNotification
    {
        /** @var DatabaseNotification|null $notification */
        $notification = $notifiable->notifications()->whereKey($id)->first();

        if ($notification === null) {
            return null;
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
            $this->forgetUnreadCount($notifiable);
        }

        return $notification;
    }

    public function markAllRead(Model $notifiable): int
    {
        $updated = $notifiable->unreadNotifications()->update(['read_at' => Carbon::now()]);
        $this->forgetUnreadCount($notifiable);

        return $updated;
    }

    /**
     * @return array{id: string, event_key: string, title: string, body: string, url: string, icon: string, read: bool, created_at: string|null, time: string}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = (array) $notification->data;

        return [
            'id' => (string) $notification->id,
            'event_key' => (string) ($data['event_key'] ?? $notification->type),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'url' => $this->safeUrl((string) ($data['url'] ?? '')),
            'icon' => (string) ($data['icon'] ?? 'bell'),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'time' => (string) $notification->created_at?->diffForHumans(),
        ];
    }

    /**
     * Only http(s) and site-relative links are clickable.
     */
    private function safeUrl(string $url): string
    {
        return preg_match('#^(https?://|/)#i', $url) && ! str_starts_with($url, '//') ? $url : '';
    }

    private function cacheKey(Model $notifiable): string
    {
        return 'notification.unread.'.str_replace('\\', '.', $notifiable->getMorphClass()).'.'.$notifiable->getKey();
    }
}
