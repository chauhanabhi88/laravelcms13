<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A module's part of GET /api/v1/app/config (plan M20 §4). Bind the implementation and tag it
 * "api.app_config" in the module's service provider; Core merges the sections under their key.
 * A provider that throws is skipped, so one module cannot take the endpoint down.
 */
interface ProvidesAppConfig
{
    public const TAG = 'api.app_config';

    /**
     * The top-level key of the section (payment, cart, features...).
     */
    public function appConfigKey(): string;

    /**
     * @return array<string, mixed>
     */
    public function appConfig(): array;
}
