<?php

declare(strict_types=1);

namespace Modules\Finance\Exceptions;

use RuntimeException;

/**
 * The payout gateway could not be reached or answered with a server error: the payout may
 * or may not exist there, so it is retried with the same idempotency key.
 */
class PayoutGatewayUnavailable extends RuntimeException {}
