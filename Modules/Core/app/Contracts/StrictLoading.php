<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Marker for models whose lazy loading must fail loudly outside production.
 *
 * Model::preventLazyLoading() is switched on in AppServiceProvider, but the violation
 * only throws for models implementing this interface (usually via MarketplaceModel),
 * so legacy modules keep working while new code is held to eager loading.
 */
interface StrictLoading {}
