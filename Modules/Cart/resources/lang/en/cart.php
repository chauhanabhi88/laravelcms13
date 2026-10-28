<?php

return [
    'titles' => [
        'carts' => 'Carts',
        'list' => 'Carts',
        'show' => 'Cart #:id',
        'details' => 'Cart details',
        'lines' => 'Lines',
        'saved' => 'Saved for later',
        'unavailable' => 'Not available',
        'totals' => 'Price details',
    ],

    'labels' => [
        'list' => 'View carts',
        'filters' => 'Filter carts',
        'show' => 'View cart',
        'back' => 'Back',
        'owner' => 'Owner',
        'type' => 'Type',
        'state' => 'State',
        'customer_search' => 'Customer name, email or mobile',
        'customer' => 'Customer',
        'guest' => 'Guest',
        'items' => 'Items',
        'lines' => ':lines lines · :units units',
        'pincode' => 'Delivery pincode',
        'coupon' => 'Coupon',
        'last_activity' => 'Last activity',
        'reminded_at' => 'Reminder sent',
        'created_at' => 'Created',
        'seller' => 'Seller',
        'product' => 'Product',
        'sku' => 'SKU',
        'qty' => 'Qty',
        'price' => 'Price',
        'total' => 'Total',
        'issues' => 'Issues',
        'none' => 'None',
        'read_only' => 'Read-only: corrections from revalidation are shown here but not saved.',
    ],

    'columns' => [
        'id' => 'ID',
        'customer' => 'Customer',
        'type' => 'Type',
        'items' => 'Items',
        'saved' => 'Saved For Later',
        'state' => 'State',
        'delivery_pincode' => 'Pincode',
        'last_activity_at' => 'Last Activity',
        'reminded_at' => 'Reminded At',
        'created_at' => 'Created At',
    ],

    'owners' => [
        'customer' => 'Customer',
        'guest' => 'Guest',
    ],

    'types' => [
        'standard' => 'Cart',
        'buy_now' => 'Buy Now',
    ],

    'states' => [
        'active' => 'Active',
        'abandoned' => 'Abandoned',
        'empty' => 'Empty',
    ],

    'messages' => [
        'data_invalid' => 'The cart was not found.',
    ],

    'settings' => [
        'group' => 'Cart',
        'max_qty_per_line' => [
            'label' => 'Max quantity per item',
            'comment' => 'Highest quantity a buyer can order of one variant (default 10).',
        ],
        'max_lines' => [
            'label' => 'Max different items',
            'comment' => 'Most distinct variants one cart can hold (default 50).',
        ],
        'free_delivery' => [
            'label' => 'Delivery charge',
            'comment' => 'Always free, free above the minimum order value below, or a flat fee per seller package below that minimum.',
        ],
        'free_delivery_min' => [
            'label' => 'Free delivery minimum (₹)',
            'comment' => 'Order (or seller package) value from which delivery is free. 0 = no minimum.',
        ],
        'delivery_fee' => [
            'label' => 'Delivery fee (₹)',
            'comment' => 'Charged per order (threshold) or per seller package (per seller package) below the minimum.',
        ],
        'cod_fee' => [
            'label' => 'Cash on Delivery fee (₹)',
            'comment' => 'Added at checkout when the buyer pays on delivery. 0 = no fee.',
        ],
        'abandoned_cart_enabled' => [
            'label' => 'Abandoned cart reminders',
            'comment' => 'Send a push reminder to customers whose cart has been idle for 3 to 24 hours (only those who allow promotional push).',
        ],
    ],
];
