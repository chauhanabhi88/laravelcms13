<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A domain exception the API can render by itself (plan M20 §3.1): the handler turns it into the
 * error envelope, with the app-specific code of its reason (price_changed -> 4091, ...) when the
 * reason has one, so controllers do not need a try/catch only to format the failure.
 */
interface ApiError
{
    /**
     * The stable, machine-readable reason (errors.reason in the envelope).
     */
    public function apiReason(): string;

    /**
     * Extra "errors" entries: field messages ({field: [message]}) or context such as the step.
     *
     * @return array<string, mixed>
     */
    public function apiErrors(): array;
}
