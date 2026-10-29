<?php

declare(strict_types=1);

namespace Modules\Dashboard\Contracts;

/**
 * A widget another module adds to the backend dashboard home. Tag the implementation with
 * self::TAG in the module's service provider; the dashboard renders it above the CMS
 * widgets when the admin holds permission(). A widget that throws is skipped.
 */
interface DashboardWidget
{
    public const TAG = 'dashboard.widgets';

    /**
     * The permission (route name) the admin needs to see the widget.
     */
    public function permission(): string;

    /**
     * The Blade view rendered with data().
     */
    public function view(): string;

    /**
     * @return array<string, mixed>
     */
    public function data(): array;

    /**
     * Extra CSS/JS paths (relative to public/) for the asset manager.
     *
     * @return list<string>
     */
    public function assets(): array;
}
