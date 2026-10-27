<?php

declare(strict_types=1);

namespace Modules\Seller\Exceptions;

use RuntimeException;

/**
 * The bank verification provider could not be used (not configured, unreachable, 5xx).
 * The account goes to manual review so the seller can upload a cancelled cheque instead.
 */
class BankVerificationUnavailable extends RuntimeException {}
