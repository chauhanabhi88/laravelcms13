<?php

declare(strict_types=1);

namespace Modules\Payment\Exceptions;

/**
 * A refund that may not be created (over-refund, payment not refundable, bad mode).
 */
class RefundException extends PaymentException {}
