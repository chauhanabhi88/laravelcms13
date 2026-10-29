<?php

declare(strict_types=1);

namespace Modules\Returns\Exceptions;

use Modules\Core\Contracts\ApiError;
use RuntimeException;

/**
 * A return action that cannot be done; the message is safe to show to the user and
 * $reasonCode is stable for the API and tests.
 */
class ReturnException extends RuntimeException implements ApiError
{
    /**
     * @param  array<string, list<string>>  $errors  field => messages, for validation-style answers
     */
    public function __construct(string $message, public readonly string $reasonCode = 'invalid', public readonly array $errors = [])
    {
        parent::__construct($message);
    }

    public static function notAllowed(): self
    {
        return new self(trans('returns::front.errors.not_allowed'), 'not_allowed');
    }

    /**
     * @param  array<string, string>  $replace
     */
    public static function field(string $field, string $key, array $replace = []): self
    {
        $message = trans('returns::front.errors.'.$key, $replace);

        return new self($message, $key, [$field => [$message]]);
    }

    public function apiReason(): string
    {
        return $this->reasonCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function apiErrors(): array
    {
        return $this->errors;
    }
}
