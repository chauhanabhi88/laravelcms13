<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

use InvalidArgumentException;

/**
 * Read access to the event catalogue (Modules/Notification/config/events.php).
 */
final class EventCatalogue
{
    public function has(string $key): bool
    {
        return is_array($this->raw($key));
    }

    /**
     * @throws InvalidArgumentException for an event that is not in the catalogue
     */
    public function get(string $key): EventDefinition
    {
        $config = $this->raw($key);

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown notification event [{$key}].");
        }

        return EventDefinition::fromConfig($key, $config);
    }

    /**
     * @return array<string, EventDefinition>
     */
    public function all(): array
    {
        $events = [];

        foreach ((array) config('notification.events', []) as $key => $config) {
            if (is_array($config)) {
                $events[(string) $key] = EventDefinition::fromConfig((string) $key, $config);
            }
        }

        return $events;
    }

    /**
     * Event keys contain dots, so they are looked up in the array, not through config() dot paths.
     *
     * @return array<string, mixed>|null
     */
    private function raw(string $key): ?array
    {
        $config = ((array) config('notification.events', []))[$key] ?? null;

        return is_array($config) ? $config : null;
    }

    /**
     * @return list<string>
     */
    public function keys(?string $audience = null): array
    {
        return array_values(array_keys(array_filter(
            $this->all(),
            fn (EventDefinition $event): bool => $audience === null || $event->audience === $audience,
        )));
    }
}
