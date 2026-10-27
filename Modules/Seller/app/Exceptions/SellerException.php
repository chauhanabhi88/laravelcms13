<?php

declare(strict_types=1);

namespace Modules\Seller\Exceptions;

use RuntimeException;

/**
 * A business-rule failure with a message safe to show the user (already translated).
 */
class SellerException extends RuntimeException {}
