<?php

declare(strict_types=1);

namespace Modules\Notification\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Customer\Models\Customer;
use Modules\Notification\Models\NotificationPreference;

/**
 * Recipient opt-outs (FR-NTF-003). Transactional messages ignore them. Without a row a
 * channel is enabled, except promotional messages to a customer who declined marketing
 * at sign-up.
 */
class PreferenceService
{
    public function allows(Model $notifiable, string $channel, string $category): bool
    {
        if (! in_array($category, (array) config('notification.opt_out_categories', []), true)) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->where('channel', $channel)
            ->where('category', $category)
            ->first();

        return $preference?->isEnabled() ?? $this->defaultFor($notifiable, $category);
    }

    /**
     * The toggles of the settings page for a notifiable's audience, with their current state.
     *
     * @return list<array{channel: string, category: string, key: string, enabled: bool}>
     */
    public function toggles(Model $notifiable, string $audience): array
    {
        $saved = NotificationPreference::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->get()
            ->keyBy(fn (NotificationPreference $preference): string => $preference->channel.'.'.$preference->category);

        return array_map(function (array $toggle) use ($saved, $notifiable): array {
            [$channel, $category] = $toggle;
            $key = $channel.'.'.$category;

            return [
                'channel' => $channel,
                'category' => $category,
                'key' => $key,
                'enabled' => $saved->get($key)?->isEnabled() ?? $this->defaultFor($notifiable, $category),
            ];
        }, $this->toggleDefinitions($audience));
    }

    /**
     * Save the settings page: every toggle of the audience, on when its key is in $enabledKeys.
     *
     * @param  list<string>  $enabledKeys  "<channel>.<category>"
     */
    public function save(Model $notifiable, string $audience, array $enabledKeys): void
    {
        DB::transaction(function () use ($notifiable, $audience, $enabledKeys): void {
            foreach ($this->toggleDefinitions($audience) as [$channel, $category]) {
                NotificationPreference::query()->updateOrCreate(
                    [
                        'notifiable_type' => $notifiable->getMorphClass(),
                        'notifiable_id' => $notifiable->getKey(),
                        'channel' => $channel,
                        'category' => $category,
                    ],
                    ['enabled' => in_array($channel.'.'.$category, $enabledKeys, true) ? config('core.yes') : config('core.no')],
                );
            }
        });
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function toggleDefinitions(string $audience): array
    {
        return array_values(array_map(
            fn (array $toggle): array => [(string) $toggle[0], (string) $toggle[1]],
            (array) config('notification.preference_toggles.'.$audience, []),
        ));
    }

    private function defaultFor(Model $notifiable, string $category): bool
    {
        if ($category === 'promotional' && $notifiable instanceof Customer) {
            return (int) $notifiable->marketing_opt_in !== (int) config('core.no');
        }

        return true;
    }
}
