<?php

declare(strict_types=1);

namespace Modules\Notification\Drivers;

/**
 * Outcome of one push to several device tokens.
 */
final readonly class PushResult
{
    /**
     * @param  list<string>  $messageIds
     * @param  list<string>  $invalidTokens  tokens the provider no longer knows (delete them)
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $sentCount,
        public array $messageIds = [],
        public array $invalidTokens = [],
        public array $errors = [],
    ) {}

    public function successful(): bool
    {
        return $this->sentCount > 0;
    }
}
