<?php

return [
    'name' => 'Finance',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'commission_rule' => 'CommissionRule',
        'fee_rule' => 'FeeRule',
        'ledger' => 'SellerLedgerEntry',
        'payout_batch' => 'PayoutBatch',
        'payout_hold' => 'PayoutHold',
        'tax_document' => 'TaxDocument',
        'cod_remittance' => 'CodRemittance',
        'reseller_withdrawal' => 'ResellerWithdrawal',
    ],

    /*
    | Fee rule codes (plan M17 §3.2). Each is a "fee_rule" row; the most specific active rule
    | charged to the seller decides the amount.
    */
    'fee_codes' => [
        'fixed_closing_fee',
        'payment_gateway_fee',
        'forward_shipping',
        'rto_shipping',
        'reverse_shipping',
        'seller_cancel_penalty',
        'sla_breach_penalty',
    ],

    /*
    | Payout gateways (admin > Settings > Finance "payout_gateway").
    */
    'payout_gateways' => ['manual', 'razorpayx', 'fake'],

    /*
    | RazorpayX payouts reuse the Seller module's RazorpayX keys (config seller.razorpayx).
    | IMPS up to imps_limit rupees, NEFT above it; UPI for a VPA beneficiary.
    */
    'razorpayx' => [
        'imps_limit' => 500000,
        'queue_if_low_balance' => true,
    ],

    /*
    | Documents (tax invoices, credit notes, fee invoices): private disk.
    */
    'documents' => [
        'disk' => 'private',
        'folder' => 'finance-documents',
    ],

    /*
    | COD remittances: a delivered COD shipment not remitted after this many days is overdue.
    */
    'cod_remittance_overdue_days' => 10,

    'seller_per_page' => 25,
    'customer_per_page' => 15,

    /*
    | Defaults for admin > Settings > Finance (read through Modules\Finance\Support\FinanceSettings).
    */
    'defaults' => [
        'payout_delay_days' => 7,
        'payout_day' => 'monday',
        'min_payout_amount' => 100,
        'tcs_rate' => '1',
        'tds_rate' => '0.1',
        'tds_rate_no_pan' => '5',
        'fee_gst_rate' => '18',
        'seller_cancel_penalty' => 0,
        'sla_breach_penalty' => 0,
        'seller_pays_forward_shipping' => 2,
        'seller_pays_rto_shipping' => 2,
        'hold_open_claims' => 0,
        'platform_legal_name' => '',
        'platform_gstin' => '',
        'platform_address' => '',
        'platform_state_id' => '',
        'payout_gateway' => 'manual',
        'reseller_min_withdrawal' => 100,
    ],
];
