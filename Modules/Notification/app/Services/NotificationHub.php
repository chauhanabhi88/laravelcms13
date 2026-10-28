<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Notification\Models\NotificationLog;
use Modules\Notification\Notifications\MarketplaceNotification;
use Modules\Notification\Support\EventCatalogue;
use Modules\Notification\Support\EventDefinition;
use Modules\Notification\Support\NotificationSettings;
use Modules\Notification\Support\RecipientResolver;
use Modules\Seller\Models\Seller;
use Modules\Seller\Models\SellerUser;
use Modules\User\Models\User;
use RuntimeException;

/**
 * The single entry point for marketplace notifications (FR-NTF-001):
 *
 *     app(NotificationHub::class)->event('order.shipped', $customer, ['order_no' => …, 'url' => …]);
 *
 * Channels = catalogue (or the $channels given) ∩ the recipient's routes ∩ global switches ∩
 * preferences. A "queued" log row is written per channel and the notification is queued
 * after commit; channels dropped here are logged as "skipped" with the reason.
 */
class NotificationHub
{
    public function __construct(
        private readonly EventCatalogue $catalogue,
        private readonly RecipientResolver $recipients,
        private readonly PreferenceService $preferences,
    ) {}

    /**
     * @param  array<string, mixed>  $data  template variables (scalars; "url" links the in-app entry)
     * @param  list<string>|null  $channels  restrict to these channels (still intersected with the routes)
     * @return array<string, string> channel => queued | skipped
     */
    public function event(string $key, Model $notifiable, array $data = [], ?array $channels = null): array
    {
        $event = $this->catalogue->get($key);
        $data = $this->normaliseData($data);
        [$send, $skipped] = $this->resolveChannels($event, $notifiable, $channels);

        foreach ($skipped as $channel => $reason) {
            $this->log($event->key, $channel, $notifiable, NotificationLog::STATUS_SKIPPED, $data, $reason);
        }

        if ($send !== []) {
            $logIds = [];

            foreach ($send as $channel) {
                $logIds[$channel] = $this->log($event->key, $channel, $notifiable, NotificationLog::STATUS_QUEUED, $data)->id;
            }

            $notifiable->notify(new MarketplaceNotification($event->key, $data, $send, $logIds));
        }

        return array_fill_keys($send, NotificationLog::STATUS_QUEUED) + array_fill_keys(array_keys($skipped), NotificationLog::STATUS_SKIPPED);
    }

    /**
     * Send a seller event to the owner login plus staff whose role the catalogue lists.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $channels
     */
    public function eventForSeller(string $key, Seller $seller, array $data = [], ?array $channels = null): int
    {
        $event = $this->catalogue->get($key);

        $users = SellerUser::query()
            ->where('seller_id', $seller->id)
            ->where('status', config('core.enabled'))
            ->whereIn('role', array_merge([SellerUser::ROLE_OWNER], $event->sellerRoles))
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $this->event($key, $user, $data, $channels);
        }

        return $users->count();
    }

    /**
     * Send an admin event to every active admin whose role grants the event's permission.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $channels
     */
    public function eventForAdmins(string $key, array $data = [], ?array $channels = null): int
    {
        $admins = $this->admins($this->catalogue->get($key));

        foreach ($admins as $admin) {
            $this->event($key, $admin, $data, $channels);
        }

        return $admins->count();
    }

    /**
     * Send a failed or skipped delivery again on its channel (admin log "retry").
     */
    public function retry(NotificationLog $log): NotificationLog
    {
        if (! $log->isRetryable()) {
            throw new RuntimeException(trans('notification::notification_log.messages.not_retryable'));
        }

        $class = Relation::getMorphedModel((string) $log->notifiable_type) ?? $log->notifiable_type;
        $notifiable = class_exists((string) $class) ? $class::query()->find($log->notifiable_id) : null;

        if (! $notifiable instanceof Model) {
            throw new RuntimeException(trans('notification::notification_log.messages.recipient_missing'));
        }

        $statuses = $this->event($log->event_key, $notifiable, $log->data(), [$log->channel]);

        return NotificationLog::query()
            ->where('event_key', $log->event_key)
            ->where('channel', $log->channel)
            ->where('notifiable_type', $log->notifiable_type)
            ->where('notifiable_id', $log->notifiable_id)
            ->latest('id')
            ->firstOr(fn () => throw new RuntimeException('Retry was not logged ('.implode(',', $statuses).').'));
    }

    /**
     * @param  list<string>|null  $only
     * @return array{0: list<string>, 1: array<string, string>} channels to send, skipped channel => reason
     */
    public function resolveChannels(EventDefinition $event, Model $notifiable, ?array $only = null): array
    {
        $known = (array) config('notification.channels', []);
        $channels = $only === null ? $event->channels : array_values(array_intersect($only, $known));
        $send = [];
        $skipped = [];

        foreach (array_unique($channels) as $channel) {
            $reason = match (true) {
                ! in_array($channel, $known, true) => 'unknown_channel',
                ! $this->recipients->isReachable($notifiable) => 'unreachable',
                $this->recipients->missingRoute($notifiable, $channel) !== null => 'no_route',
                $channel === 'push' && ! NotificationSettings::pushEnabled() => 'disabled',
                $channel === 'sms' && ! $event->isOptional() && ! NotificationSettings::transactionalSmsEnabled() => 'disabled',
                ! $this->preferences->allows($notifiable, $channel, $event->category) => 'opted_out',
                default => null,
            };

            if ($reason === null) {
                $send[] = $channel;
            } else {
                $skipped[$channel] = $reason;
            }
        }

        return [$send, $skipped];
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(EventDefinition $event): Collection
    {
        if ($event->adminPermission === null) {
            throw new InvalidArgumentException("Event [{$event->key}] has no admin_permission.");
        }

        return User::query()
            ->with('role')
            ->where('status', config('core.enabled'))
            ->orderBy('id')
            ->get()
            ->filter(function (User $admin) use ($event): bool {
                $permissions = json_decode((string) $admin->role?->permissions, true);

                return is_array($permissions) && in_array($event->adminPermission, $permissions, true);
            })
            ->values();
    }

    /**
     * Keep scalar template variables only (the notification is serialised onto the queue).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, scalar|null>
     */
    private function normaliseData(array $data): array
    {
        $normalised = [];

        foreach ($data as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \Stringable) {
                $value = (string) $value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format('j M Y');
            }

            if ($value === null || is_scalar($value)) {
                $normalised[(string) $key] = $value;
            }
        }

        return $normalised;
    }

    /**
     * @param  array<string, scalar|null>  $data
     */
    private function log(string $eventKey, string $channel, Model $notifiable, string $status, array $data, ?string $error = null): NotificationLog
    {
        return NotificationLog::query()->create([
            'event_key' => $eventKey,
            'channel' => $channel,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'destination' => $status === NotificationLog::STATUS_SKIPPED && $error === 'no_route' ? null : $this->recipients->destination($notifiable, $channel),
            'status' => $status,
            'error' => $error,
            'payload' => ['data' => $data],
        ]);
    }
}
