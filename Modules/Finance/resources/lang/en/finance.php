<?php

$crudPermissions = fn (string $what): array => [
    'index' => "View {$what}",
    'filters' => "Filter {$what}",
    'create' => "Create {$what}",
    'edit' => "Edit {$what}",
    'delete' => "Delete {$what}",
];

return [
    'titles' => [
        'commission_rule' => 'Commission Rules',
        'commission_rule_create' => 'New Commission Rule',
        'commission_rule_edit' => 'Edit Commission Rule',
        'fee_rule' => 'Fee Rules',
        'fee_rule_create' => 'New Fee Rule',
        'fee_rule_edit' => 'Edit Fee Rule',
        'ledger' => 'Seller Ledger',
        'payout_batch' => 'Payout Batches',
        'payout_hold' => 'Payout Holds',
        'tax_document' => 'Invoices & Credit Notes',
        'tcs_report' => 'TCS Report (GSTR-8)',
        'cod_remittance' => 'COD Remittances',
        'reseller_withdrawal' => 'Reseller Withdrawals',
    ],

    'buttons' => [
        'add_rule' => 'Add Rule',
        'back' => 'Back',
        'delete' => 'Delete',
        'download' => 'Download PDF',
        'edit' => 'Edit',
        'export_csv' => 'Export CSV',
        'mark_failed' => 'Mark failed',
        'mark_paid' => 'Mark paid',
        'show' => 'Show',
        'view' => 'View',
    ],

    'labels' => [
        'utr' => 'Bank UTR',
    ],

    'hints' => [
        'commission_rule' => 'The most specific active rule wins: seller + category, then seller, then category (nearest ancestor), then global. Orders keep the commission of the day they were placed.',
        'fee_rule' => 'Only rules charged to the seller are deducted from payouts. Percent shipping rules apply to the courier\'s actual charge.',
    ],

    'messages' => [
        'not_found' => 'The record was not found.',
        'rule_created' => 'The rule was created.',
        'rule_updated' => 'The rule was updated.',
        'rule_deleted' => 'The rule was deleted.',
        'confirm_delete' => 'Delete this rule? Orders already placed keep their commission.',
    ],

    'options' => [
        'enabled' => 'Active',
        'disabled' => 'Inactive',
        'hold_active' => 'Active',
        'hold_released' => 'Released',
    ],

    'rule' => [
        'scope' => 'Scope',
        'catalog_category_id' => 'Category',
        'seller_id' => 'Seller',
        'type' => 'Type',
        'value' => 'Value',
        'applies_to' => 'Applies to',
        'code' => 'Fee',
        'charged_to' => 'Charged to',
        'effective_from' => 'Effective from',
        'effective_to' => 'Effective to',
        'status' => 'Status',
        'any' => '— Any —',
        'scope_hint' => 'Category applies to the category and everything below it. Seller and seller + category need a seller.',
        'fee_hint' => 'Flat: rupees per item (closing fee) or per event (shipping, penalty). Percent: of the item price, or of the courier\'s actual charge for shipping fees.',
    ],

    'filters' => [
        'scope' => 'Scope',
        'status' => 'Status',
        'code' => 'Fee',
        'charged_to' => 'Charged to',
        'type' => 'Type',
        'direction' => 'Direction',
        'state' => 'State',
        'search_commission_rule' => 'Seller code or name',
        'search_fee_rule' => 'Seller code or name',
        'search_ledger' => 'Seller, order no or description',
        'search_payout_batch' => 'Batch no',
        'search_payout_hold' => 'Seller code or name',
        'search_tax_document' => 'Number or seller',
        'search_cod_remittance' => 'Reference or UTR',
        'search_reseller_withdrawal' => 'Withdrawal no',
    ],

    'columns' => [
        'commission_rule' => ['id' => 'ID', 'scope' => 'Scope', 'target' => 'Category / Seller', 'value' => 'Commission', 'applies_to' => 'Applies To', 'effective_from' => 'From', 'effective_to' => 'To', 'status' => 'Status'],
        'fee_rule' => ['id' => 'ID', 'code' => 'Fee', 'scope' => 'Scope', 'target' => 'Category / Seller', 'value' => 'Value', 'charged_to' => 'Charged To', 'effective_from' => 'From', 'status' => 'Status'],
        'ledger' => ['id' => 'ID', 'posted_at' => 'Posted At', 'seller' => 'Seller', 'order' => 'Order', 'type' => 'Type', 'description' => 'Description', 'amount' => 'Amount', 'tax_amount' => 'GST', 'status' => 'Status', 'eligible_on' => 'Payable On'],
        'payout_batch' => ['id' => 'ID', 'batch_no' => 'Batch No', 'cycle_end' => 'Cycle End', 'gateway' => 'Gateway', 'sellers_count' => 'Sellers', 'total_amount' => 'Total', 'status' => 'Status', 'created_at' => 'Built At'],
        'payout_hold' => ['id' => 'ID', 'seller' => 'Seller', 'reason' => 'Reason', 'placed_at' => 'Placed At', 'released_at' => 'Released At'],
        'tax_document' => ['id' => 'ID', 'number' => 'Number', 'type' => 'Type', 'seller' => 'Seller', 'document_date' => 'Date', 'taxable_value' => 'Taxable', 'tax' => 'GST', 'total' => 'Total'],
        'cod_remittance' => ['id' => 'ID', 'remittance_ref' => 'Reference', 'remittance_date' => 'Date', 'utr' => 'UTR', 'amount' => 'Amount', 'lines_count' => 'AWBs', 'mismatch_count' => 'Mismatches', 'status' => 'Status'],
        'reseller_withdrawal' => ['id' => 'ID', 'withdrawal_no' => 'Withdrawal No', 'customer' => 'Reseller', 'amount' => 'Amount', 'beneficiary_label' => 'To', 'status' => 'Status', 'utr' => 'UTR', 'created_at' => 'Requested At'],
    ],

    'scopes' => [
        'global' => 'Global',
        'category' => 'Category',
        'seller' => 'Seller',
        'seller_category' => 'Seller + category',
    ],

    'commission_types' => [
        'percent' => 'Percent',
        'flat_per_item' => 'Flat per item',
    ],

    'applies_to' => [
        'item_price' => 'Item price (GST inclusive)',
        'taxable_value' => 'Taxable value',
    ],

    'fee_types' => [
        'percent' => 'Percent',
        'flat' => 'Flat',
    ],

    'fee_codes' => [
        'fixed_closing_fee' => 'Fixed closing fee',
        'payment_gateway_fee' => 'Payment gateway fee',
        'forward_shipping' => 'Forward shipping',
        'rto_shipping' => 'RTO shipping',
        'reverse_shipping' => 'Reverse (return) shipping',
        'seller_cancel_penalty' => 'Seller cancellation penalty',
        'sla_breach_penalty' => 'SLA breach penalty',
    ],

    'charged_to' => [
        'seller' => 'Seller',
        'platform' => 'Platform',
    ],

    'ledger_types' => [
        'sale' => 'Sale',
        'commission' => 'Commission',
        'commission_reversal' => 'Commission reversal',
        'platform_fee' => 'Platform fee',
        'platform_fee_reversal' => 'Platform fee reversal',
        'logistics_forward' => 'Shipping',
        'logistics_rto' => 'RTO shipping',
        'logistics_reverse' => 'Return shipping',
        'return_reversal' => 'Return',
        'penalty' => 'Penalty',
        'claim_credit' => 'Claim credit',
        'tcs' => 'TCS',
        'tds' => 'TDS',
        'tcs_reversal' => 'TCS reversal',
        'tds_reversal' => 'TDS reversal',
        'adjustment' => 'Adjustment',
        'payout' => 'Payout',
    ],

    'ledger_statuses' => [
        'pending' => 'In return window',
        'eligible' => 'Payable',
        'on_hold' => 'On hold',
        'settled' => 'Settled',
    ],

    'directions' => [
        'credit' => 'Credit (owed to the seller)',
        'debit' => 'Debit (deducted)',
    ],

    'batch_statuses' => [
        'draft' => 'Draft',
        'approved' => 'Approved',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'partially_failed' => 'Partially failed',
    ],

    'payout_statuses' => [
        'scheduled' => 'Scheduled',
        'on_hold' => 'On hold',
        'processing' => 'Processing',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'reversed' => 'Reversed',
    ],

    'document_types' => [
        'tax_invoice' => 'Tax invoices',
        'credit_note' => 'Credit notes',
        'fee_invoice' => 'Fee invoices',
    ],

    'remittance_statuses' => [
        'received' => 'Received',
        'reconciled' => 'Reconciled',
        'mismatch' => 'Mismatch',
    ],

    'withdrawal_statuses' => [
        'pending' => 'Requested',
        'processing' => 'Processing',
        'paid' => 'Paid',
        'failed' => 'Failed',
    ],

    'permissions' => [
        'groups' => [
            'commission_rule' => 'Finance · Commission rules',
            'fee_rule' => 'Finance · Fee rules',
            'ledger' => 'Finance · Seller ledger',
            'payout_batch' => 'Finance · Payouts',
            'payout_hold' => 'Finance · Payout holds',
            'reports' => 'Finance · TCS and COD reconciliation',
            'reseller_withdrawal' => 'Finance · Reseller withdrawals',
            'tax_document' => 'Finance · Invoices and credit notes',
        ],
        'commission_rule' => $crudPermissions('commission rules'),
        'fee_rule' => $crudPermissions('fee rules'),
        'ledger' => [
            'index' => 'View the seller ledger',
            'filters' => 'Filter the seller ledger',
            'export' => 'Export the seller ledger',
            'adjust' => 'Request manual adjustments',
            'approve_adjustment' => 'Approve or reject manual adjustments',
        ],
        'payout_batch' => [
            'index' => 'View payout batches',
            'filters' => 'Filter payout batches',
            'show' => 'View a payout batch',
            'build' => 'Build a payout batch',
            'approve' => 'Approve a payout batch',
            'process' => 'Send a payout batch to the gateway',
            'export_bank_file' => 'Download the bank transfer file',
            'upload_utr' => 'Upload bank UTRs',
        ],
        'seller_payout' => [
            'retry' => 'Retry a failed payout',
            'mark_paid' => 'Mark a payout paid',
        ],
        'payout_hold' => [
            'index' => 'View payout holds',
            'filters' => 'Filter payout holds',
            'store' => 'Hold a seller\'s payouts',
            'release' => 'Release a payout hold',
        ],
        'tcs_report' => [
            'index' => 'View the TCS report',
            'export' => 'Export the TCS report',
        ],
        'cod_remittance' => [
            'index' => 'View COD remittances',
            'filters' => 'Filter COD remittances',
            'upload' => 'Upload COD remittances',
            'show' => 'View a COD remittance',
        ],
        'reseller_withdrawal' => [
            'index' => 'View reseller withdrawals',
            'filters' => 'Filter reseller withdrawals',
            'mark_paid' => 'Mark a withdrawal paid',
            'mark_failed' => 'Mark a withdrawal failed',
        ],
        'tax_document' => [
            'index' => 'View invoices and credit notes',
            'filters' => 'Filter invoices and credit notes',
            'download' => 'Download invoices and credit notes',
            'regenerate' => 'Re-render PDFs and issue fee invoices',
        ],
    ],

    'settings' => [
        'group' => 'Finance',
        'payout_gateway' => ['label' => 'Payout gateway', 'comment' => 'Manual: download the bank file and upload UTRs. RazorpayX: payouts are sent and confirmed by webhook (uses the RAZORPAYX_* keys).'],
        'payout_delay_days' => ['label' => 'Payout delay (days)', 'comment' => 'Days after the return window closes before a sale becomes payable.'],
        'payout_day' => ['label' => 'Payout day', 'comment' => 'Weekday the payout batch is built.'],
        'min_payout_amount' => ['label' => 'Minimum payout (Rs)', 'comment' => 'Smaller balances wait for the next cycle.'],
        'tcs_rate' => ['label' => 'TCS rate (%)', 'comment' => 'Collected on the net taxable value of GST-registered sellers (CGST Act §52).'],
        'tds_rate' => ['label' => 'TDS rate (%)', 'comment' => 'Section 194-O, on the gross amount of sellers with a PAN.'],
        'tds_rate_no_pan' => ['label' => 'TDS rate without PAN (%)', 'comment' => 'Used when the seller has no PAN on file.'],
        'fee_gst_rate' => ['label' => 'GST on fees (%)', 'comment' => 'Charged to the seller on commission and platform fees.'],
        'seller_cancel_penalty' => ['label' => 'Seller cancellation penalty (Rs)', 'comment' => '0 = none. Overrides the fee rule when set.'],
        'sla_breach_penalty' => ['label' => 'SLA breach penalty (Rs)', 'comment' => '0 = none. Overrides the fee rule when set.'],
        'seller_pays_forward_shipping' => ['label' => 'Seller pays forward shipping', 'comment' => 'Yes: the courier\'s actual charge is deducted on delivery. No: the platform absorbs it (unless a fee rule says otherwise).'],
        'seller_pays_rto_shipping' => ['label' => 'Seller pays RTO shipping', 'comment' => 'Yes: freight + RTO charge of undelivered packages is deducted.'],
        'hold_open_claims' => ['label' => 'Hold payouts at open claims', 'comment' => 'Number of open claims that holds a seller\'s payouts (0 = never).'],
        'reseller_min_withdrawal' => ['label' => 'Reseller minimum withdrawal (Rs)', 'comment' => 'Smallest amount a reseller can withdraw.'],
        'platform_legal_name' => ['label' => 'Platform legal name', 'comment' => 'Supplier name on fee invoices.'],
        'platform_gstin' => ['label' => 'Platform GSTIN', 'comment' => 'Printed on fee invoices.'],
        'platform_address' => ['label' => 'Platform address', 'comment' => 'Printed on fee invoices.'],
        'platform_state_id' => ['label' => 'Platform state ID', 'comment' => 'Directory state id: CGST + SGST for sellers in the same state, IGST otherwise.'],
    ],
];
