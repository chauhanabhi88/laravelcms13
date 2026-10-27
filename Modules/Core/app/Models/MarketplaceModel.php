<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\StrictLoading;

/**
 * Base model for the marketplace modules: lazy loading throws outside production.
 *
 * Authenticatable models that cannot extend this class implement StrictLoading directly.
 */
abstract class MarketplaceModel extends Model implements StrictLoading {}
