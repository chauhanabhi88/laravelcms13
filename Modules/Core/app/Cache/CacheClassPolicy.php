<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

/**
 * Which classes a cached payload may rebuild when config('cache.serializable_classes') is
 * restricted (false or a list).
 *
 * Laravel 13 ships that setting as false so a leaked APP_KEY cannot be turned into a
 * gadget-chain attack through the cache. The repository cache decorators, however, cache
 * Eloquent models by design; with no classes allowed every cached model came back as
 * __PHP_Incomplete_Class (TypeError on the second request of /c/{slug}).
 *
 * The policy reads the class names out of the serialized payload and allows only the
 * application's own data classes (Modules\*\Models|Enums|DTO|Support\*) and plain
 * framework data classes (collections, dates, stdClass). Everything else, vendor
 * gadget classes included, stays blocked and unserializes as __PHP_Incomplete_Class.
 */
final class CacheClassPolicy
{
    /**
     * Framework / PHP data classes that cached values legitimately contain.
     */
    private const DATA_CLASSES = [
        'stdClass',
        'ArrayObject',
        'DateTime',
        'DateTimeImmutable',
        'DateTimeZone',
        'DateInterval',
        'Carbon\Carbon',
        'Carbon\CarbonImmutable',
        'Carbon\CarbonTimeZone',
        'Carbon\CarbonInterval',
        'Illuminate\Support\Carbon',
        'Illuminate\Support\Collection',
        'Illuminate\Database\Eloquent\Collection',
        'Illuminate\Database\Eloquent\Casts\ArrayObject',
        'Illuminate\Notifications\DatabaseNotification',
        'Illuminate\Notifications\DatabaseNotificationCollection',
        'Modules\Core\Support\Money',
    ];

    /**
     * Application data namespaces: Modules\<Module>\Models|Enums|DTO|Support\... (Support holds
     * cached value objects such as Catalog's ResolvedAttribute). None of these classes define
     * __destruct / __wakeup / __unserialize / __call; keep it that way.
     */
    private const MODULE_PATTERN = '/^Modules\\\\[A-Za-z0-9_]+\\\\(Models|Enums|DTO|Support)\\\\[A-Za-z0-9_\\\\]+$/';

    /**
     * O:<len>:"Class":… (objects), C:<len>:"Class":… (Serializable) and E:<len>:"Class:Case"; (enums).
     */
    private const CLASS_TOKEN = '/[OCE]:\d+:"([A-Za-z_][A-Za-z0-9_\\\\]*)(?::[A-Za-z0-9_]+)?"/';

    /**
     * The allowed_classes option for unserialize(): the configured list plus every class in
     * the payload the policy accepts. A class name that only appears inside string data is
     * harmless to allow: unserialize() never instantiates it.
     *
     * @param  array<int, class-string>|bool  $configured
     * @return array<int, string>|bool
     */
    public static function allowedClasses(string $payload, array|bool $configured): array|bool
    {
        if ($configured === true) {
            return true;
        }

        $allowed = $configured === false ? [] : array_values($configured);

        if (preg_match_all(self::CLASS_TOKEN, $payload, $matches)) {
            foreach (array_unique($matches[1]) as $class) {
                if (self::isAllowed($class)) {
                    $allowed[] = $class;
                }
            }
        }

        return array_values(array_unique($allowed));
    }

    public static function isAllowed(string $class): bool
    {
        return in_array($class, self::DATA_CLASSES, true) || preg_match(self::MODULE_PATTERN, $class) === 1;
    }
}
