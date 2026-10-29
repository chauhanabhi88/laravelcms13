<?php

declare(strict_types=1);

namespace Modules\Promotion\Exceptions;

use Modules\Promotion\DTO\CouponEvaluation;
use RuntimeException;

/**
 * A coupon cannot be applied. $reason is stable for the API (CouponEvaluation constants,
 * "not_found", "login_required"); the message is for the buyer.
 */
class CouponException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = CouponEvaluation::NOT_FOUND)
    {
        parent::__construct($message);
    }

    public static function notFound(string $code): self
    {
        return new self(trans('promotion::front.errors.not_found', ['code' => $code]), CouponEvaluation::NOT_FOUND);
    }

    public static function fromEvaluation(CouponEvaluation $evaluation): self
    {
        return new self((string) $evaluation->message, (string) $evaluation->reason);
    }
}
