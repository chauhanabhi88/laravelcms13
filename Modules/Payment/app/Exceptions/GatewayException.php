<?php

declare(strict_types=1);

namespace Modules\Payment\Exceptions;

use RuntimeException;

/**
 * The payment provider could not be reached or answered with an error.
 */
class GatewayException extends RuntimeException {}
