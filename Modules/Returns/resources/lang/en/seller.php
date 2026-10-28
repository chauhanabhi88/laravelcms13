<?php

return [
    'menu' => 'Returns',
    'title' => 'Returns',
    'hint' => 'Check returned items when they reach you. A good item goes back to stock and the customer is refunded; a damaged or wrong one goes to our team, and you can raise a claim.',
    'detail_title' => 'Return :return_no',
    'back' => 'All returns',
    'view_order' => 'View order',
    'search' => 'Return or order no',
    'search_button' => 'Search',
    'empty_title' => 'No returns here',
    'empty_text' => 'Returns of your orders show up in these tabs.',
    'items' => 'Items',
    'exchange_for' => 'Customer wants: :sku',
    'progress' => 'Progress',
    'pickup' => 'Reverse pickup',

    'tabs' => [
        'to_check' => 'To check',
        'incoming' => 'Incoming',
        'closed' => 'Closed',
    ],

    'columns' => [
        'return_no' => 'Return',
        'order' => 'Order',
        'items' => 'Items',
        'reason' => 'Reason',
        'status' => 'Status',
        'created_at' => 'Requested',
    ],

    'item_statuses' => [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],

    'qc' => [
        'heading' => 'Quality check',
        'hint' => 'Open the package and check the item against the customer\'s reason and photos.',
        'result' => 'Result',
        'results' => [
            'passed' => 'Item is fine',
            'failed' => 'Item is damaged, used or wrong',
        ],
        'note' => 'Note',
        'submit' => 'Save result',
    ],

    'claim' => [
        'heading' => 'Raise a claim',
        'hint' => 'Claims can be raised within :hours hours after the return reaches you.',
        'raise' => 'Raise a claim for compensation',
        'reason' => 'Claim reason',
        'choose_reason' => 'Choose a reason',
        'description' => 'What is wrong?',
        'photos' => 'Photos',
        'submit' => 'Submit claim',
        'list' => 'Claims',
    ],

    'messages' => [
        'qc_passed' => 'Return :return_no accepted. The item is back in stock.',
        'qc_failed' => 'Return :return_no sent to our team for a decision.',
        'claim_opened' => 'Claim submitted. Our team will review it.',
    ],

    'errors' => [
        'claim_closed' => 'A claim is no longer possible for this return.',
    ],
];
