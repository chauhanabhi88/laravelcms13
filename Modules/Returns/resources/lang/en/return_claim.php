<?php

return [
    'titles' => [
        'claims' => 'Seller claims',
        'list' => 'Seller Claims',
        'show' => 'Claim #:id',
    ],

    'columns' => [
        'id' => 'ID',
        'reference' => 'Return / Order',
        'source' => 'Source',
        'seller' => 'Seller',
        'reason' => 'Reason',
        'status' => 'Status',
        'resolution_amount' => 'Credit',
        'created_at' => 'Raised At',
        'decided_at' => 'Decided At',
    ],

    'labels' => [
        'list' => 'Claims list',
        'filters' => 'Filter claims',
        'show' => 'View claim',
        'decide' => 'Decide',
        'status' => 'Status',
        'reason' => 'Reason',
        'search' => 'Return no or order no',
        'back' => 'Back',
        'return' => 'Return',
        'source' => 'Source',
        'order' => 'Order',
        'seller' => 'Seller',
        'created_at' => 'Raised at',
        'decision' => 'Decision',
        'accept' => 'Accept and credit the seller',
        'reject' => 'Reject',
        'resolution_amount' => 'Amount to credit (Rs)',
        'note' => 'Note to the seller',
    ],

    'sources' => [
        'return' => 'Customer return',
        'rto' => 'Returned undelivered',
    ],

    'reasons' => [
        'damaged_return' => 'Returned damaged',
        'wrong_item_returned' => 'A different item came back',
        'used_item' => 'Used or worn item',
        'missing_parts' => 'Parts or accessories missing',
    ],

    'statuses' => [
        'open' => 'Open',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],

    'messages' => [
        'not_found' => 'Claim not found.',
        'accepted' => 'Claim accepted.',
        'rejected' => 'Claim rejected.',
        'already_decided' => 'This claim was decided already.',
    ],
];
