<?php

declare(strict_types=1);

namespace Modules\Payment\DTO;

use Modules\Payment\Models\PaymentRefund;

/**
 * The provider's answer to a refund request.
 */
final readonly class RefundResult
{
    /**
     * @param  string  $status  a PaymentRefund::STATUS_* value
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $status,
        public ?string $gatewayRefundId = null,
        public ?string $error = null,
        public array $raw = [],
    ) {}

    /**
     * Read a Razorpay refund entity: "processed" is final, "failed" failed, anything else
     * ("pending") is still processing.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function fromRazorpay(array $entity): self
    {
        $status = match ($entity['status'] ?? null) {
            'processed' => PaymentRefund::STATUS_PROCESSED,
            'failed' => PaymentRefund::STATUS_FAILED,
            default => PaymentRefund::STATUS_PROCESSING,
        };

        return new self($status, isset($entity['id']) ? (string) $entity['id'] : null, null, $entity);
    }

    public static function failed(string $error, array $raw = []): self
    {
        return new self(PaymentRefund::STATUS_FAILED, null, $error, $raw);
    }
}
