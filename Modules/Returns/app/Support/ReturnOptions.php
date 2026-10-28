<?php

declare(strict_types=1);

namespace Modules\Returns\Support;

use Modules\Returns\Models\ReturnClaim;
use Modules\Returns\Models\ReturnRequest;

/**
 * Labels, chip variants and admin badges of the return / claim enums.
 */
final class ReturnOptions
{
    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return self::translate(ReturnRequest::STATUSES, 'returns::returns.statuses');
    }

    public static function status(string $status): string
    {
        return self::statuses()[$status] ?? $status;
    }

    /**
     * @return array<string, string>
     */
    public static function types(): array
    {
        return self::translate(ReturnRequest::TYPES, 'returns::returns.types');
    }

    /**
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return self::translate((array) config('returns.reasons', []), 'returns::returns.reasons');
    }

    public static function reason(string $reason): string
    {
        return self::reasons()[$reason] ?? $reason;
    }

    /**
     * @return array<string, string>
     */
    public static function refundModes(): array
    {
        return self::translate(['source', 'bank', 'upi'], 'returns::returns.refund_modes');
    }

    /**
     * @return array<string, string>
     */
    public static function refundStatuses(): array
    {
        return self::translate([ReturnRequest::REFUND_INITIATED, ReturnRequest::REFUND_REFUNDED, ReturnRequest::REFUND_FAILED], 'returns::returns.refund_statuses');
    }

    /**
     * @return array<string, string>
     */
    public static function claimReasons(): array
    {
        return self::translate((array) config('returns.claim_reasons', []), 'returns::return_claim.reasons');
    }

    /**
     * @return array<string, string>
     */
    public static function claimStatuses(): array
    {
        return self::translate(ReturnClaim::STATUSES, 'returns::return_claim.statuses');
    }

    /**
     * <x-theme::chip> variant for a return status.
     */
    public static function chip(string $status): string
    {
        return match ($status) {
            ReturnRequest::STATUS_REFUNDED, ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED, ReturnRequest::STATUS_QC_PASSED => 'success',
            ReturnRequest::STATUS_REJECTED, ReturnRequest::STATUS_QC_FAILED, ReturnRequest::STATUS_CANCELLED => 'danger',
            ReturnRequest::STATUS_REQUESTED => 'deal',
            ReturnRequest::STATUS_CLOSED => 'neutral',
            default => 'accent',
        };
    }

    /**
     * Admin (Bootstrap) badge class suffix for a return status.
     */
    public static function badge(string $status): string
    {
        return match ($status) {
            ReturnRequest::STATUS_REFUNDED, ReturnRequest::STATUS_EXCHANGE_ORDER_CREATED, ReturnRequest::STATUS_QC_PASSED => 'success',
            ReturnRequest::STATUS_REJECTED, ReturnRequest::STATUS_QC_FAILED, ReturnRequest::STATUS_CANCELLED => 'danger',
            ReturnRequest::STATUS_REQUESTED => 'warning',
            ReturnRequest::STATUS_CLOSED => 'secondary',
            default => 'info',
        };
    }

    public static function claimBadge(string $status): string
    {
        return match ($status) {
            ReturnClaim::STATUS_ACCEPTED => 'success',
            ReturnClaim::STATUS_REJECTED => 'danger',
            default => 'warning',
        };
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function translate(array $values, string $prefix): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = trans($prefix.'.'.$value);
        }

        return $options;
    }
}
