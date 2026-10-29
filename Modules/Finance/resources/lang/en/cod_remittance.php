<?php

return [
    'show_title' => 'COD remittance :ref',
    'summary' => 'Remitted on :date: :amount for :lines AWB(s), :mismatches mismatch(es).',
    'upload_title' => 'Upload a remittance file',
    'upload_hint' => 'CSV with the columns remittance_ref, remittance_date, utr, awb, amount (one row per AWB). Remittances already imported are skipped.',
    'overdue_title' => 'Overdue: :count delivered COD shipment(s) not remitted after :days days',
    'overdue_none' => 'No delivered COD shipment is overdue.',
    'matched' => 'Matched',

    'buttons' => [
        'upload' => 'Upload',
    ],

    'columns' => [
        'awb' => 'AWB',
        'order' => 'Order',
        'amount' => 'Remitted',
        'expected' => 'Expected',
        'result' => 'Result',
    ],

    'issues' => [
        'unknown_awb' => 'Unknown AWB',
        'amount_mismatch' => 'Amount differs',
        'not_cod' => 'Not a COD shipment',
        'not_delivered' => 'Not delivered',
        'duplicate' => 'Already remitted',
    ],

    'messages' => [
        'imported' => ':count remittance(s) imported.',
        'unreadable' => 'The file could not be read.',
        'bad_header' => 'The first row must name the columns :columns.',
    ],
];
