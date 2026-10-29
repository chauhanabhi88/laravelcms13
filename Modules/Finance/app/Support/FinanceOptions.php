<?php

declare(strict_types=1);

namespace Modules\Finance\Support;

use Modules\Finance\Models\CodRemittance;
use Modules\Finance\Models\CommissionRule;
use Modules\Finance\Models\FeeRule;
use Modules\Finance\Models\PayoutBatch;
use Modules\Finance\Models\ResellerWithdrawal;
use Modules\Finance\Models\SellerLedgerEntry;
use Modules\Finance\Models\SellerPayout;
use Modules\Finance\Models\TaxDocument;

/**
 * Translated option lists and badge / chip colours for the finance screens.
 */
final class FinanceOptions
{
    /**
     * @return array<string, string>
     */
    public static function scopes(): array
    {
        return self::labels(CommissionRule::SCOPES, 'scopes');
    }

    /**
     * @return array<string, string>
     */
    public static function commissionTypes(): array
    {
        return self::labels(CommissionRule::TYPES, 'commission_types');
    }

    /**
     * @return array<string, string>
     */
    public static function appliesTo(): array
    {
        return self::labels(CommissionRule::APPLIES_TO, 'applies_to');
    }

    /**
     * @return array<string, string>
     */
    public static function feeCodes(): array
    {
        return self::labels((array) config('finance.fee_codes', []), 'fee_codes');
    }

    /**
     * @return array<string, string>
     */
    public static function feeTypes(): array
    {
        return self::labels(FeeRule::TYPES, 'fee_types');
    }

    /**
     * @return array<string, string>
     */
    public static function chargedTo(): array
    {
        return self::labels(FeeRule::CHARGED_TO, 'charged_to');
    }

    /**
     * @return array<string, string>
     */
    public static function ledgerTypes(): array
    {
        return self::labels(SellerLedgerEntry::TYPES, 'ledger_types');
    }

    /**
     * @return array<string, string>
     */
    public static function ledgerStatuses(): array
    {
        return self::labels(SellerLedgerEntry::STATUSES, 'ledger_statuses');
    }

    /**
     * @return array<string, string>
     */
    public static function directions(): array
    {
        return self::labels([SellerLedgerEntry::CREDIT, SellerLedgerEntry::DEBIT], 'directions');
    }

    /**
     * @return array<string, string>
     */
    public static function batchStatuses(): array
    {
        return self::labels(PayoutBatch::STATUSES, 'batch_statuses');
    }

    /**
     * @return array<string, string>
     */
    public static function payoutStatuses(): array
    {
        return self::labels(SellerPayout::STATUSES, 'payout_statuses');
    }

    /**
     * @return array<string, string>
     */
    public static function documentTypes(): array
    {
        return self::labels(TaxDocument::TYPES, 'document_types');
    }

    /**
     * @return array<string, string>
     */
    public static function remittanceStatuses(): array
    {
        return self::labels(CodRemittance::STATUSES, 'remittance_statuses');
    }

    /**
     * @return array<string, string>
     */
    public static function withdrawalStatuses(): array
    {
        return self::labels(ResellerWithdrawal::STATUSES, 'withdrawal_statuses');
    }

    /**
     * @return array<string, string>
     */
    public static function enabledOptions(): array
    {
        return [
            (string) config('core.enabled') => trans('finance::finance.options.enabled'),
            (string) config('core.disabled') => trans('finance::finance.options.disabled'),
        ];
    }

    /**
     * Select options of a grid's filters.
     *
     * @return array<string, array<string, string>>
     */
    public static function filterOptions(string $grid): array
    {
        return match ($grid) {
            'commission_rule' => ['scope' => self::scopes(), 'status' => self::enabledOptions()],
            'fee_rule' => ['code' => self::feeCodes(), 'charged_to' => self::chargedTo(), 'status' => self::enabledOptions()],
            'ledger' => ['type' => self::ledgerTypes(), 'status' => self::ledgerStatuses(), 'direction' => self::directions()],
            'payout_batch' => ['status' => self::batchStatuses()],
            'payout_hold' => ['state' => ['active' => trans('finance::finance.options.hold_active'), 'released' => trans('finance::finance.options.hold_released')]],
            'tax_document' => ['type' => self::documentTypes()],
            'cod_remittance' => ['status' => self::remittanceStatuses()],
            'reseller_withdrawal' => ['status' => self::withdrawalStatuses()],
            default => [],
        };
    }

    public static function label(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $key = 'finance::finance.'.$group.'.'.$value;
        $label = trans($key);

        return $label === $key ? $value : $label;
    }

    /**
     * Bootstrap badge colour (admin) for any finance status.
     */
    public static function badge(?string $status): string
    {
        return match ($status) {
            'paid', 'settled', 'completed', 'reconciled', 'eligible', 'available', 'approved' => 'success',
            'failed', 'reversed', 'mismatch', 'partially_failed', 'rejected' => 'danger',
            'on_hold', 'processing', 'pending' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Theme chip variant (supplier panel / storefront) for any finance status.
     */
    public static function chip(?string $status): string
    {
        return match (self::badge($status)) {
            'success' => 'success',
            'danger' => 'danger',
            'warning' => 'deal',
            default => 'neutral',
        };
    }

    /**
     * @param  list<string>  $values
     * @return array<string, string>
     */
    private static function labels(array $values, string $group): array
    {
        $options = [];

        foreach ($values as $value) {
            $options[$value] = trans('finance::finance.'.$group.'.'.$value);
        }

        return $options;
    }
}
