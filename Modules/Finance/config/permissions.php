<?php

// Wrapped so the helper closure never leaks into the scope that includes this file.
return (static function (): array {
    $crud = fn (string $entity): array => [
        "admin.{$entity}.index" => "finance::finance.permissions.{$entity}.index",
        "admin.{$entity}.filters" => "finance::finance.permissions.{$entity}.filters",
        "admin.{$entity}.create" => "finance::finance.permissions.{$entity}.create",
        "admin.{$entity}.edit" => "finance::finance.permissions.{$entity}.edit",
        "admin.{$entity}.delete" => "finance::finance.permissions.{$entity}.delete",
    ];

    return [
        'finance::finance.permissions.groups.commission_rule' => $crud('commission_rule'),
        'finance::finance.permissions.groups.fee_rule' => $crud('fee_rule'),
        'finance::finance.permissions.groups.ledger' => [
            'admin.ledger.index' => 'finance::finance.permissions.ledger.index',
            'admin.ledger.filters' => 'finance::finance.permissions.ledger.filters',
            'admin.ledger.export' => 'finance::finance.permissions.ledger.export',
            'admin.ledger.adjust' => 'finance::finance.permissions.ledger.adjust',
            'admin.ledger.approve_adjustment' => 'finance::finance.permissions.ledger.approve_adjustment',
        ],
        'finance::finance.permissions.groups.payout_batch' => [
            'admin.payout_batch.index' => 'finance::finance.permissions.payout_batch.index',
            'admin.payout_batch.filters' => 'finance::finance.permissions.payout_batch.filters',
            'admin.payout_batch.show' => 'finance::finance.permissions.payout_batch.show',
            'admin.payout_batch.build' => 'finance::finance.permissions.payout_batch.build',
            'admin.payout_batch.approve' => 'finance::finance.permissions.payout_batch.approve',
            'admin.payout_batch.process' => 'finance::finance.permissions.payout_batch.process',
            'admin.payout_batch.export_bank_file' => 'finance::finance.permissions.payout_batch.export_bank_file',
            'admin.payout_batch.upload_utr' => 'finance::finance.permissions.payout_batch.upload_utr',
            'admin.seller_payout.retry' => 'finance::finance.permissions.seller_payout.retry',
            'admin.seller_payout.mark_paid' => 'finance::finance.permissions.seller_payout.mark_paid',
        ],
        'finance::finance.permissions.groups.payout_hold' => [
            'admin.payout_hold.index' => 'finance::finance.permissions.payout_hold.index',
            'admin.payout_hold.filters' => 'finance::finance.permissions.payout_hold.filters',
            'admin.payout_hold.store' => 'finance::finance.permissions.payout_hold.store',
            'admin.payout_hold.release' => 'finance::finance.permissions.payout_hold.release',
        ],
        'finance::finance.permissions.groups.reports' => [
            'admin.tcs_report.index' => 'finance::finance.permissions.tcs_report.index',
            'admin.tcs_report.export' => 'finance::finance.permissions.tcs_report.export',
            'admin.cod_remittance.index' => 'finance::finance.permissions.cod_remittance.index',
            'admin.cod_remittance.filters' => 'finance::finance.permissions.cod_remittance.filters',
            'admin.cod_remittance.upload' => 'finance::finance.permissions.cod_remittance.upload',
            'admin.cod_remittance.show' => 'finance::finance.permissions.cod_remittance.show',
        ],
        'finance::finance.permissions.groups.reseller_withdrawal' => [
            'admin.reseller_withdrawal.index' => 'finance::finance.permissions.reseller_withdrawal.index',
            'admin.reseller_withdrawal.filters' => 'finance::finance.permissions.reseller_withdrawal.filters',
            'admin.reseller_withdrawal.mark_paid' => 'finance::finance.permissions.reseller_withdrawal.mark_paid',
            'admin.reseller_withdrawal.mark_failed' => 'finance::finance.permissions.reseller_withdrawal.mark_failed',
        ],
        'finance::finance.permissions.groups.tax_document' => [
            'admin.tax_document.index' => 'finance::finance.permissions.tax_document.index',
            'admin.tax_document.filters' => 'finance::finance.permissions.tax_document.filters',
            'admin.tax_document.download' => 'finance::finance.permissions.tax_document.download',
            'admin.tax_document.regenerate' => 'finance::finance.permissions.tax_document.regenerate',
        ],
    ];
})();
