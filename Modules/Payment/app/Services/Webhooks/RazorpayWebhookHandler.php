<?php

declare(strict_types=1);

namespace Modules\Payment\Services\Webhooks;

use Modules\Payment\DTO\GatewayPayment;
use Modules\Payment\DTO\RefundResult;
use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentAttempt;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Models\WebhookEvent;
use Modules\Payment\Services\PaymentService;
use Modules\Payment\Services\RefundService;

/**
 * Applies a stored, signature-verified Razorpay webhook (plan M11 §5.2):
 * - payment.captured / order.paid → markCaptured (idempotent, so the callback and the
 *   webhook may arrive in either order);
 * - payment.failed → an attempt row (no state change once captured);
 * - refund.processed / refund.failed → finalise the refund.
 * Anything else, or an entity we do not know, is "ignored".
 */
class RazorpayWebhookHandler
{
    public const HANDLED_EVENTS = ['payment.captured', 'order.paid', 'payment.failed', 'refund.processed', 'refund.failed'];

    public function __construct(
        private readonly PaymentService $payments,
        private readonly RefundService $refunds,
    ) {}

    /**
     * @return string the WebhookEvent status to store (processed | ignored)
     */
    public function handle(WebhookEvent $event): string
    {
        $payload = (array) ($event->payload ?? []);
        $type = (string) ($payload['event'] ?? $event->event_type);
        $entities = (array) ($payload['payload'] ?? []);

        return match ($type) {
            'payment.captured', 'order.paid' => $this->captured((array) ($entities['payment']['entity'] ?? [])),
            'payment.failed' => $this->failed((array) ($entities['payment']['entity'] ?? [])),
            'refund.processed', 'refund.failed' => $this->refund((array) ($entities['refund']['entity'] ?? []), $type === 'refund.processed'),
            default => WebhookEvent::STATUS_IGNORED,
        };
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function captured(array $entity): string
    {
        $payment = $this->paymentFor($entity);

        if ($payment === null) {
            return WebhookEvent::STATUS_IGNORED;
        }

        $this->payments->markCaptured($payment, GatewayPayment::fromRazorpay($entity), PaymentAttempt::SOURCE_WEBHOOK);

        return WebhookEvent::STATUS_PROCESSED;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function failed(array $entity): string
    {
        $payment = $this->paymentFor($entity);

        if ($payment === null) {
            return WebhookEvent::STATUS_IGNORED;
        }

        $gatewayPayment = GatewayPayment::fromRazorpay($entity);
        $this->payments->recordFailure($payment, $gatewayPayment->id ?: null, $gatewayPayment->errorCode, $gatewayPayment->errorDescription, $entity, PaymentAttempt::SOURCE_WEBHOOK);

        return WebhookEvent::STATUS_PROCESSED;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function refund(array $entity, bool $processed): string
    {
        $refundId = (string) ($entity['id'] ?? '');
        $localId = $entity['notes']['refund_id'] ?? null;

        $refund = $refundId === '' ? null : PaymentRefund::query()->where('gateway_refund_id', $refundId)->first();
        $refund ??= is_numeric($localId) ? PaymentRefund::query()->find((int) $localId) : null;

        if ($refund === null) {
            return WebhookEvent::STATUS_IGNORED;
        }

        $result = $processed
            ? new RefundResult(PaymentRefund::STATUS_PROCESSED, $refundId ?: null, null, $entity)
            : new RefundResult(PaymentRefund::STATUS_FAILED, $refundId ?: null, (string) ($entity['error_description'] ?? $entity['status_details']['description'] ?? 'Refund failed at the gateway'), $entity);

        $this->refunds->applyResult($refund, $result);

        return WebhookEvent::STATUS_PROCESSED;
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    protected function paymentFor(array $entity): ?Payment
    {
        $orderId = (string) ($entity['order_id'] ?? '');

        if ($orderId !== '') {
            return Payment::query()->where('gateway_order_id', $orderId)->first();
        }

        $paymentId = (string) ($entity['id'] ?? '');

        return $paymentId === '' ? null : Payment::query()->where('gateway_payment_id', $paymentId)->first();
    }
}
