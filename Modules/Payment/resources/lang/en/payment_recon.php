<?php

return [
    'titles' => [
        'reconciliation' => 'Settlement reconciliation',
        'list' => 'Settlement reconciliation',
    ],

    'labels' => [
        'list' => 'View reconciliation',
        'filters' => 'Filter reconciliation',
        'resolve' => 'Resolve mismatch',
        'issue' => 'Issue',
        'state' => 'State',
        'search' => 'Gateway or settlement id',
        'note' => 'Note',
        'confirm_resolve' => 'Mark this mismatch as resolved?',
        'help' => 'Settled Razorpay payments and refunds that do not match our records. The daily job adds new rows; resolve them once investigated.',
    ],

    'columns' => [
        'id' => 'ID',
        'settlement_date' => 'Settlement Date',
        'entity_type' => 'Type',
        'entity_id' => 'Gateway ID',
        'issue' => 'Issue',
        'gateway_amount' => 'Gateway Amount',
        'local_amount' => 'Our Amount',
        'state' => 'State',
        'created_at' => 'Found At',
    ],

    'issues' => [
        'missing_local' => 'Not in our records',
        'amount_mismatch' => 'Amount differs',
        'status_mismatch' => 'Status differs',
    ],

    'states' => [
        'open' => 'Open',
        'resolved' => 'Resolved',
    ],

    'messages' => [
        'not_found' => 'The mismatch was not found.',
        'resolved' => 'The mismatch is marked as resolved.',
    ],
];
