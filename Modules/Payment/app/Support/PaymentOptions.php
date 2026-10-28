<?php

declare(strict_types=1);

namespace Modules\Payment\Support;

use Modules\Payment\Models\Payment;
use Modules\Payment\Models\PaymentReconItem;
use Modules\Payment\Models\PaymentRefund;
use Modules\Payment\Models\WebhookEvent;

/**
 * Labels and badge colours for the admin grids and filters.
 */
final class PaymentOptions
{
    public const RECON_OPEN = 'open';

    public const RECON_RESOLVED = 'resolved';

    /**
     * @return array<string, string>
     */
    public static function methods(): array
    {
        return self::labels([Payment::METHOD_RAZORPAY, Payment::METHOD_COD, Payment::METHOD_FAKE], 'payment::payment.methods.');
    }

    /**
     * @return array<string, string>
     */
    public static function paymentStatuses(): array
    {
        return self::labels([
            Payment::STATUS_CREATED,
            Payment::STATUS_ATTEMPTED,
            Payment::STATUS_CAPTURED,
            Payment::STATUS_FAILED,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_PARTIALLY_REFUNDED,
            Payment::STATUS_REFUNDED,
            Payment::STATUS_PENDING,
            Payment::STATUS_COLLECTED,
            Payment::STATUS_REMITTED,
        ], 'payment::payment.statuses.');
    }

    public static function paymentBadge(string $status): string
    {
        return match ($status) {
            Payment::STATUS_CAPTURED, Payment::STATUS_COLLECTED, Payment::STATUS_REMITTED => 'success',
            Payment::STATUS_FAILED => 'danger',
            Payment::STATUS_CANCELLED => 'secondary',
            Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED => 'info',
            default => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function refundStatuses(): array
    {
        return self::labels([
            PaymentRefund::STATUS_PENDING,
            PaymentRefund::STATUS_PROCESSING,
            PaymentRefund::STATUS_PROCESSED,
            PaymentRefund::STATUS_FAILED,
        ], 'payment::payment_refund.statuses.');
    }

    public static function refundBadge(string $status): string
    {
        return match ($status) {
            PaymentRefund::STATUS_PROCESSED => 'success',
            PaymentRefund::STATUS_FAILED => 'danger',
            PaymentRefund::STATUS_PROCESSING => 'info',
            default => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function refundModes(): array
    {
        return self::labels([PaymentRefund::MODE_SOURCE, PaymentRefund::MODE_BANK, PaymentRefund::MODE_UPI], 'payment::payment_refund.modes.');
    }

    /**
     * @return array<string, string>
     */
    public static function webhookStatuses(): array
    {
        return self::labels(WebhookEvent::STATUSES, 'payment::webhook_event.statuses.');
    }

    public static function webhookBadge(string $status): string
    {
        return match ($status) {
            WebhookEvent::STATUS_PROCESSED => 'success',
            WebhookEvent::STATUS_FAILED => 'danger',
            WebhookEvent::STATUS_IGNORED => 'secondary',
            default => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function webhookProviders(): array
    {
        return self::labels(['razorpay', 'razorpayx', 'shiprocket'], 'payment::webhook_event.providers.');
    }

    /**
     * @return array<string, string>
     */
    public static function reconIssues(): array
    {
        return self::labels(PaymentReconItem::ISSUES, 'payment::payment_recon.issues.');
    }

    /**
     * @return array<string, string>
     */
    public static function reconStates(): array
    {
        return self::labels([self::RECON_OPEN, self::RECON_RESOLVED], 'payment::payment_recon.states.');
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function labels(array $values, string $prefix): array
    {
        return array_combine($values, array_map(fn (string $value): string => trans($prefix.$value), $values));
    }
}
