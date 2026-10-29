<?php

declare(strict_types=1);

namespace Modules\Review\Exceptions;

use RuntimeException;

/**
 * A review action that cannot be done; the message is safe to show to the user and
 * $reasonCode is stable for the API and tests.
 */
class ReviewException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors  field => messages, for validation-style answers
     */
    public function __construct(string $message, public readonly string $reasonCode = 'invalid', public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function because(string $key): self
    {
        return new self(trans('review::front.errors.'.$key), $key);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function field(string $field, string $key, array $replace = []): self
    {
        $message = trans('review::front.errors.'.$key, $replace);

        return new self($message, $key, [$field => [$message]]);
    }
}
