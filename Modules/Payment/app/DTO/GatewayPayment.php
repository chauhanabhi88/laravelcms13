<?php

declare(strict_types=1);

namespace Modules\Payment\DTO;

/**
 * A payment as the provider reports it (Razorpay payment entity).
 */
final readonly class GatewayPayment
{
    public const STATUS_CREATED = 'created';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $instrumentMeta  masked details only
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $orderId,
        public string $status,
        public int $amountPaise,
        public string $currency = 'INR',
        public ?string $instrument = null,
        public array $instrumentMeta = [],
        public ?string $errorCode = null,
        public ?string $errorDescription = null,
        public array $raw = [],
    ) {}

    /**
     * Build from a Razorpay payment entity (API response or webhook payload).
     *
     * Only masked instrument details are kept: card network/last4/type, the VPA, bank or wallet.
     *
     * @param  array<string, mixed>  $entity
     */
    public static function fromRazorpay(array $entity): self
    {
        $card = (array) ($entity['card'] ?? []);
        $meta = array_filter([
            'network' => $card['network'] ?? null,
            'last4' => $card['last4'] ?? null,
            'card_type' => $card['type'] ?? null,
            'vpa' => $entity['vpa'] ?? null,
            'bank' => $entity['bank'] ?? null,
            'wallet' => $entity['wallet'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return new self(
            id: (string) ($entity['id'] ?? ''),
            orderId: isset($entity['order_id']) ? (string) $entity['order_id'] : null,
            status: (string) ($entity['status'] ?? ''),
            amountPaise: (int) ($entity['amount'] ?? 0),
            currency: (string) ($entity['currency'] ?? 'INR'),
            instrument: isset($entity['method']) ? (string) $entity['method'] : null,
            instrumentMeta: $meta,
            errorCode: isset($entity['error_code']) ? (string) $entity['error_code'] : null,
            errorDescription: isset($entity['error_description']) ? (string) $entity['error_description'] : null,
            raw: $entity,
        );
    }

    /**
     * Money has been taken (a later refund does not undo the capture).
     */
    public function isCaptured(): bool
    {
        return in_array($this->status, [self::STATUS_CAPTURED, self::STATUS_REFUNDED], true);
    }

    public function isAuthorized(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
