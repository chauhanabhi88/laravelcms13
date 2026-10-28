<?php

return [
    'statuses' => [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pickup_scheduled' => 'Pickup scheduled',
        'picked_up' => 'Picked up',
        'received' => 'Received by seller',
        'qc_passed' => 'Quality check passed',
        'qc_failed' => 'Quality check failed',
        'refund_initiated' => 'Refund initiated',
        'refunded' => 'Refunded',
        'exchange_order_created' => 'Exchange shipped',
        'cancelled' => 'Cancelled',
        'closed' => 'Closed',
    ],

    'types' => [
        'return' => 'Return',
        'exchange' => 'Exchange',
    ],

    'reasons' => [
        'wrong_size' => 'Size doesn\'t fit',
        'defective' => 'Defective or not working',
        'damaged' => 'Arrived damaged',
        'not_as_described' => 'Different from the description or photos',
        'wrong_item' => 'Received a different item',
        'missing_item' => 'Item or part missing',
        'quality' => 'Quality is not as expected',
        'changed_mind' => 'No longer needed',
    ],

    'refund_modes' => [
        'source' => 'Original payment method',
        'bank' => 'Bank account',
        'upi' => 'UPI ID',
    ],

    'refund_statuses' => [
        'initiated' => 'Refund initiated',
        'refunded' => 'Refund completed',
        'failed' => 'Refund failed, we are retrying',
    ],

    'timeline' => [
        'requested' => 'Return requested',
        'approved' => 'Approved',
        'pickup_scheduled' => 'Pickup scheduled',
        'picked_up' => 'Picked up',
        'received' => 'Received by seller',
        'qc_passed' => 'Quality check passed',
        'refunded' => 'Refunded',
        'refund_initiated' => 'Refund initiated',
        'exchange_order_created' => 'Replacement on its way',
        'rejected' => 'Not approved',
        'cancelled' => 'Cancelled',
        'qc_failed' => 'Quality check failed',
        'closed' => 'Closed',
    ],

    'history' => [
        'requested' => 'Return :return_no requested',
        'auto_approved' => 'Approved automatically',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled by the customer',
        'force_refund' => 'Refunded by customer support',
        'exchange_for' => 'Exchange for return :return_no',
        'items_kept' => 'Return :return_no closed, items stay with the customer',
        'all_returned' => 'All items returned (:return_no)',
    ],

    'attention' => [
        'approval_failed' => 'Automatic approval failed; approve or reject by hand.',
        'pickup_failed' => 'Reverse pickup not booked: :error',
        'awb_pending' => 'Reverse pickup booked without an AWB yet: :error',
        'pickup_cancelled' => 'The courier cancelled the pickup; schedule it again.',
        'lost' => 'The reverse shipment was lost or returned; decide the refund by hand.',
        'qc_failed' => 'The seller reported a failed quality check; decide the refund.',
        'exchange_unavailable' => 'The exchange size is out of stock; refunding instead.',
        'refund_not_started' => 'The refund could not start: :error',
        'refund_failed' => 'The refund failed: :reason',
    ],

    'refund_reason' => 'Return :return_no',
    'rto_refund_reason' => 'Package :order_no returned to seller undelivered',

    'settings' => [
        'group' => 'Returns',
        'auto_approve' => [
            'label' => 'Approve returns automatically',
            'comment' => 'When the rules below pass, the reverse pickup is booked at once; otherwise CX reviews the request.',
        ],
        'auto_approve_max_return_rate' => [
            'label' => 'Highest return rate approved automatically (%)',
            'comment' => 'Returns raised in the last 90 days as a share of delivered packages. Also the limit for refunds at pickup.',
        ],
        'auto_approve_max_value' => [
            'label' => 'Highest return value approved automatically (Rs)',
            'comment' => 'Returns worth more go to CX.',
        ],
        'refund_on_pickup_for_trusted' => [
            'label' => 'Refund trusted customers at pickup',
            'comment' => 'Trusted customers get their refund (or exchange) as soon as the courier collects the item, before the seller checks it.',
        ],
        'trusted_min_delivered_orders' => [
            'label' => 'Delivered packages to count as trusted',
            'comment' => 'A customer with at least this many delivered packages (and a return rate within the limit) is trusted.',
        ],
        'max_images' => [
            'label' => 'Photos per return',
            'comment' => 'Most photos a customer (or seller claim) may add, 1–10.',
        ],
        'claim_window_hours' => [
            'label' => 'Seller claim window (hours)',
            'comment' => 'Hours after a return reaches the seller in which they may raise a claim.',
        ],
        'refund_delivery_fee_on_seller_fault' => [
            'label' => 'Refund the delivery charge when the seller is at fault',
            'comment' => 'For damaged, defective, wrong, missing or misdescribed items.',
        ],
    ],
];
