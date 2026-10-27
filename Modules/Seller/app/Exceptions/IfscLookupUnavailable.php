<?php

declare(strict_types=1);

namespace Modules\Seller\Exceptions;

use RuntimeException;

/**
 * The IFSC directory could not be reached; callers fall back to the format check.
 */
class IfscLookupUnavailable extends RuntimeException {}
