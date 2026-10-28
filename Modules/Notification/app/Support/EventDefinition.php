<?php

declare(strict_types=1);

namespace Modules\Notification\Support;

/**
 * One entry of the event catalogue (config notification.events).
 */
final readonly class EventDefinition
{
    /**
     * @param  list<string>  $channels
     * @param  list<string>  $vars
     * @param  list<string>  $sellerRoles
     */
    public function __construct(
        public string $key,
        public string $audience,
        public array $channels,
        public string $category,
        public string $icon = 'bell',
        public array $vars = [],
        public array $sellerRoles = [],
        public ?string $adminPermission = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            audience: (string) ($config['audience'] ?? 'customer'),
            channels: array_values(array_map('strval', (array) ($config['channels'] ?? []))),
            category: (string) ($config['category'] ?? 'transactional'),
            icon: (string) ($config['icon'] ?? 'bell'),
            vars: array_values(array_map('strval', (array) ($config['vars'] ?? []))),
            sellerRoles: array_values(array_map('strval', (array) ($config['seller_roles'] ?? []))),
            adminPermission: isset($config['admin_permission']) ? (string) $config['admin_permission'] : null,
        );
    }

    /**
     * Whether recipients may switch this event off (anything but transactional).
     */
    public function isOptional(): bool
    {
        return in_array($this->category, (array) config('notification.opt_out_categories', []), true);
    }

    public function isPromotional(): bool
    {
        return $this->category === 'promotional';
    }

    /**
     * Slug of the Mail module template: dots become dashes, then the audience,
     * e.g. order.shipped → order-shipped-customer.
     */
    public function mailSlug(): string
    {
        return self::mailSlugFor($this->key, $this->audience);
    }

    public static function mailSlugFor(string $eventKey, string $audience): string
    {
        return str_replace(['.', '_'], '-', $eventKey).'-'.$audience;
    }
}
