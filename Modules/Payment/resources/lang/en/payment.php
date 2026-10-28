<?php

return [
    'titles' => [
        'payments' => 'Payments',
        'list' => 'Transactions',
        'show' => 'Payment #:id',
        'details' => 'Payment details',
        'attempts' => 'Attempts',
        'refunds' => 'Refunds',
        'history' => 'Status history',
    ],

    'labels' => [
        'list' => 'View transactions',
        'filters' => 'Filter transactions',
        'show' => 'View payment',
        'back' => 'Back',
        'method' => 'Method',
        'status' => 'Status',
        'search' => 'Order no, gateway id, customer email or mobile',
        'order' => 'Order',
        'customer' => 'Customer',
        'amount' => 'Amount',
        'refunded' => 'Refunded',
        'refundable' => 'Still refundable',
        'instrument' => 'Instrument',
        'gateway_order' => 'Gateway order',
        'gateway_payment' => 'Gateway payment',
        'captured_at' => 'Captured at',
        'expires_at' => 'Window ends',
        'failure_reason' => 'Failure reason',
        'cod_otp' => 'COD OTP',
        'cod_otp_verified' => 'Verified :date',
        'cod_otp_pending' => 'Waiting for the buyer',
        'not_required' => 'Not required',
        'created_at' => 'Created',
        'source' => 'Source',
        'error' => 'Error',
        'from' => 'From',
        'to' => 'To',
        'actor' => 'By',
        'reason' => 'Reason',
        'date' => 'Date',
        'mode' => 'Mode',
        'reference' => 'Reference',
        'none' => 'None',
    ],

    'columns' => [
        'id' => 'ID',
        'order_no' => 'Order',
        'customer' => 'Customer',
        'method' => 'Method',
        'status' => 'Status',
        'amount' => 'Amount',
        'amount_refunded' => 'Refunded',
        'instrument' => 'Instrument',
        'gateway_payment_id' => 'Gateway Payment',
        'captured_at' => 'Captured At',
        'created_at' => 'Created At',
    ],

    'methods' => [
        'razorpay' => 'Razorpay',
        'cod' => 'Cash on Delivery',
        'fake' => 'Test gateway',
    ],

    'statuses' => [
        'created' => 'Created',
        'attempted' => 'Attempted',
        'captured' => 'Captured',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partly refunded',
        'pending' => 'COD pending',
        'collected' => 'COD collected',
        'remitted' => 'COD remitted',
    ],

    'messages' => [
        'not_found' => 'The payment was not found.',
        'invalid_transition' => 'A payment cannot move from :from to :to.',
    ],

    'settings' => [
        'group' => 'Payment',
        'cod_enabled' => [
            'label' => 'Cash on Delivery',
            'comment' => 'Offer Cash on Delivery at checkout (still subject to the pincode, value and customer rules below).',
        ],
        'cod_min_value' => [
            'label' => 'COD minimum order value (₹)',
            'comment' => 'Orders below this value cannot be paid on delivery. 0 = no minimum.',
        ],
        'cod_max_value' => [
            'label' => 'COD maximum order value (₹)',
            'comment' => 'Orders above this value must be paid online (default 5000).',
        ],
        'cod_daily_limit' => [
            'label' => 'COD orders per customer per day',
            'comment' => 'Most Cash on Delivery orders one customer can place in a day (default 3, 0 = no limit).',
        ],
        'cod_otp_threshold' => [
            'label' => 'COD OTP from order value (₹)',
            'comment' => 'COD orders from this value need an OTP confirmation (0 = every COD order).',
        ],
        'cod_otp_new_customers' => [
            'label' => 'COD OTP for new customers',
            'comment' => 'Also ask for an OTP when the customer has never received an order.',
        ],
        'payment_window_minutes' => [
            'label' => 'Online payment window (minutes)',
            'comment' => 'How long a buyer can retry a failed online payment before the order is cancelled (default 30).',
        ],
        'cod_otp_window_minutes' => [
            'label' => 'COD OTP window (minutes)',
            'comment' => 'COD orders whose OTP is not verified within this time are cancelled (default 15).',
        ],
    ],
];
