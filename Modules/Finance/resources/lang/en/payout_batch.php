<?php

return [
    'show_title' => 'Payout batch :batch',
    'hint' => 'Batches are built on the payout day from payable balances (return window + payout delay over, no hold, verified bank account). A different admin approves them before money moves.',
    'utr_title' => 'Upload UTRs',
    'utr_hint' => 'CSV with the payout number in the first column and the bank UTR in the second. Each listed payout is marked paid.',
    'confirm_approve' => 'Approve this batch for payment?',
    'confirm_process' => 'Send these payouts to the payout gateway now?',

    'buttons' => [
        'build' => 'Build payout batch',
        'approve' => 'Approve',
        'process' => 'Send payouts',
        'bank_file' => 'Bank transfer file',
        'upload_utr' => 'Upload',
    ],

    'columns' => [
        'payout' => 'Payout',
        'seller' => 'Seller',
        'account' => 'Bank account',
        'amount' => 'Amount',
        'status' => 'Status',
        'utr' => 'UTR',
    ],

    'labels' => [
        'cycle' => 'Cycle',
        'total' => 'Total (sellers)',
        'gateway' => 'Gateway',
        'built_by' => 'Built by',
        'approved_by' => 'Approved by',
        'scheduler' => 'Scheduler',
    ],

    'messages' => [
        'built' => 'Batch :batch was built. Another finance admin must approve it.',
        'nothing_payable' => 'No seller has a payable balance above the minimum.',
        'build_running' => 'A batch is being built right now. Try again in a minute.',
        'not_draft' => 'Only a draft batch can be approved.',
        'not_approved' => 'The batch must be approved first.',
        'maker_checker' => 'You built this batch, so another admin must approve it.',
        'approved' => 'Batch :batch was approved.',
        'processing' => 'The payouts of :batch are being sent.',
        'utr_unknown' => 'Payout :payout is not in this batch or has no UTR.',
        'utrs_applied' => ':count payout(s) marked paid.',
    ],
];
