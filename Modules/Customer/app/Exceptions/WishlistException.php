<?php

declare(strict_types=1);

namespace Modules\Customer\Exceptions;

use RuntimeException;

/**
 * A wishlist write that cannot be done (unavailable product, list full). User-safe message.
 */
final class WishlistException extends RuntimeException {}
