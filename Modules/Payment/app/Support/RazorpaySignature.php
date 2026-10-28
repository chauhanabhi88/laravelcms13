<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

/**
 * Razorpay HMAC-SHA256 signatures:
 * - checkout callback: HMAC(order_id + "|" + payment_id, key secret);
 * - webhook: HMAC(raw request body, webhook secret), sent in X-Razorpay-Signature.
 *
 * Comparisons are constant-time; an empty secret or signature never verifies.
 */
final class RazorpaySignature
{
    public static function forCallback(string $gatewayOrderId, string $gatewayPaymentId, string $secret): string
    {
        return hash_hmac('sha256', $gatewayOrderId.'|'.$gatewayPaymentId, $secret);
    }

    public static function verifyCallback(string $gatewayOrderId, string $gatewayPaymentId, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '' || $gatewayOrderId === '' || $gatewayPaymentId === '') {
            return false;
        }

        return hash_equals(self::forCallback($gatewayOrderId, $gatewayPaymentId, $secret), $signature);
    }

    public static function forWebhook(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public static function verifyWebhook(string $body, string $signature, string $secret): bool
    {
        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(self::forWebhook($body, $secret), $signature);
    }
}
