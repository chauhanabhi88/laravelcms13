<?php

return [
    'status' => [
        'sales_order' => [
            'placed' => 'Placed',
            'pending_payment' => 'Awaiting payment',
            'pending_cod_verification' => 'Awaiting COD confirmation',
            'confirmed' => 'Confirmed',
            'partially_shipped' => 'Partially shipped',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'partially_cancelled' => 'Partially cancelled',
            'cancelled' => 'Cancelled',
            'completed' => 'Completed',
        ],
        'seller_order' => [
            'pending_payment' => 'Awaiting payment',
            'pending_acceptance' => 'Order placed',
            'ready_to_ship' => 'Being packed',
            'packed' => 'Packed',
            'shipped' => 'Shipped',
            'out_for_delivery' => 'Out for delivery',
            'ndr' => 'Delivery attempt failed',
            'delivered' => 'Delivered',
            'rto_initiated' => 'Returning to seller',
            'rto_delivered' => 'Returned to seller',
            'return_requested' => 'Return requested',
            'returned' => 'Returned',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
    ],

    'payment_methods' => [
        'cod' => 'Cash on Delivery',
        'razorpay' => 'Paid online',
        'fake' => 'Paid online (test)',
    ],

    'settings' => [
        'group' => 'Order',
        'rto_block_threshold' => [
            'label' => 'RTOs before COD is blocked',
            'comment' => 'Orders returned to origin within the window below that block Cash on Delivery for the customer (0 = never block). Admins can lift the block from the customer grid.',
        ],
        'rto_window_days' => [
            'label' => 'RTO window (days)',
            'comment' => 'How far back RTOs count towards the block.',
        ],
        'max_open_cod_orders' => [
            'label' => 'Open COD orders per customer',
            'comment' => 'Undelivered Cash on Delivery orders a customer may have at once (0 = no limit).',
        ],
        'reseller_max_markup_multiplier' => [
            'label' => 'Reseller price cap (× MRP)',
            'comment' => 'The highest price a reseller may set for their customer, as a multiple of MRP (e.g. 1.5).',
        ],
        'allow_customer_cancel_after_packed' => [
            'label' => 'Customers may cancel packed orders',
            'comment' => 'Whether customers can still cancel once the seller has packed the order (before pickup).',
        ],
    ],
];
