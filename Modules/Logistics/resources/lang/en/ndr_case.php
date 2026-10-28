<?php

return [
    'titles' => [
        'ndr_cases' => 'NDR',
        'list' => 'Failed deliveries (NDR)',
    ],
    'labels' => [
        'list' => 'List',
        'filters' => 'Filter',
        'act' => 'Take action',
        'search' => 'AWB or sub-order no',
        'status' => 'Status',
        'customer_action' => 'Action',
        'action' => 'Action',
        'phone' => 'New phone',
        'address1' => 'New address line 1',
        'address2' => 'New address line 2',
        'comments' => 'Comments',
        'submit' => 'Apply',
        'actioned_by' => 'By',
    ],
    'columns' => [
        'id' => 'ID',
        'awb' => 'AWB',
        'sub_order_no' => 'Sub-order No',
        'reason' => 'Reason',
        'attempts' => 'Attempts',
        'customer_action' => 'Action',
        'status' => 'Status',
        'created_at' => 'Raised At',
    ],
    'statuses' => [
        'open' => 'Open',
        'actioned' => 'Actioned',
        'closed' => 'Closed',
    ],
    'actions' => [
        'reattempt' => 'Re-attempt delivery',
        'change_address' => 'Re-attempt at new address / phone',
        'cancel' => 'Cancel (return to seller)',
    ],
    'messages' => [
        'not_found' => 'NDR case not found.',
        'not_open' => 'This case has already been actioned.',
        'invalid_action' => 'Choose a valid action.',
        'actioned' => 'Action sent to the courier.',
        'auto_rto' => 'Returned to origin automatically after the maximum delivery attempts.',
    ],
];
