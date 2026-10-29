<?php

return [
    'menu' => [
        'section' => 'Payments',
        'overview' => 'Overview',
        'statement' => 'Statement',
        'payouts' => 'Payouts',
        'invoices' => 'Invoices',
    ],

    'hold_reasons' => [
        'manual' => 'Our finance team has put your payouts on hold. Please check your email or contact support.',
        'bank' => 'Your bank account is not verified yet.',
        'status' => 'Your supplier account is not active.',
        'claims' => 'You have open claims under review.',
    ],

    'overview' => [
        'title' => 'Payments',
        'next_payout' => 'Next payout (:date)',
        'pending' => 'Pending (return window)',
        'on_hold' => 'On hold',
        'paid_30' => 'Paid in the last 30 days',
        'hint' => 'Sales become payable after the return window closes plus the payout delay. Amounts are net of commission, fees, TCS and TDS.',
        'on_hold_title' => 'Your payouts are on hold',
        'weekly' => 'Weekly payouts',
        'upcoming' => 'Coming up',
        'upcoming_empty' => 'Nothing is waiting in a return window.',
        'payable_from' => 'payable from :date',
        'recent_payouts' => 'Recent payouts',
        'all_payouts' => 'All payouts',
    ],

    'statement' => [
        'title' => 'Statement',
        'from' => 'From',
        'to' => 'To',
        'type' => 'Type',
        'all_types' => 'All types',
        'order' => 'Order no',
        'apply' => 'Apply',
        'export' => 'Export CSV',
        'empty_title' => 'No entries',
        'empty_text' => 'Entries appear here when your orders are delivered.',
        'columns' => [
            'date' => 'Date',
            'order' => 'Order',
            'description' => 'Description',
            'credit' => 'Credit',
            'debit' => 'Debit',
            'status' => 'Status',
            'balance' => 'Balance',
        ],
    ],

    'payouts' => [
        'title' => 'Payouts',
        'show_title' => 'Payout :payout',
        'to_account' => 'To :account',
        'other_entries' => 'Other entries',
        'empty_title' => 'No payouts yet',
        'empty_text' => 'Your first payout is made on the payout day after your first sales clear the return window.',
        'columns' => [
            'payout' => 'Payout',
            'date' => 'Date',
            'account' => 'Bank account',
            'status' => 'Status',
            'utr' => 'UTR',
            'amount' => 'Amount',
        ],
    ],

    'settlement' => [
        'title' => 'Settlement of :order',
        'net' => 'Net settlement',
        'entries' => 'Entries',
        'not_posted' => 'Nothing is posted yet: the settlement appears once the order is delivered.',
        'statuses' => [
            'not_posted' => 'Not delivered yet',
            'pending' => 'In return window',
            'eligible' => 'Payable',
            'on_hold' => 'On hold',
            'settled' => 'Paid',
        ],
    ],

    'invoices' => [
        'title' => 'Invoices',
        'download' => 'Download',
        'empty_title' => 'No documents',
        'empty_text' => [
            'tax_invoice' => 'Tax invoices are issued when you accept an order.',
            'credit_note' => 'Credit notes are issued when a return is refunded.',
            'fee_invoice' => 'Fee invoices are issued monthly when commission or fees were charged.',
        ],
        'columns' => [
            'number' => 'Number',
            'date' => 'Date',
            'reference' => 'Order / period',
            'taxable' => 'Taxable',
            'gst' => 'GST',
            'total' => 'Total',
        ],
    ],
];
