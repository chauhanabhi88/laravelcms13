<?php

return [
    'permission_groups' => [
        'coupons' => 'Promotions: coupons & offers',
        'flash_deals' => 'Promotions: flash deals',
        'reports' => 'Promotions: report',
    ],

    'filters' => [
        'type' => 'Type',
        'kind' => 'Kind',
        'funded_by' => 'Funded by',
        'status' => 'Status',
        'state' => 'State',
        'search_coupon' => 'Code, name or batch',
        'search_flash_deal' => 'Deal name',
    ],

    'options' => [
        'types' => [
            'percent' => 'Percent off',
            'flat' => 'Flat amount off',
            'free_delivery' => 'Free delivery',
        ],
        'scopes' => [
            'all' => 'Whole cart',
            'category' => 'Categories',
            'seller' => 'Sellers',
            'product' => 'Products',
            'brand' => 'Brands',
        ],
        'funding' => [
            'platform' => 'Platform',
            'seller' => 'Seller',
        ],
        'channels' => [
            'web' => 'Website',
            'app' => 'App (API)',
        ],
        'payment_methods' => [
            'prepaid' => 'Prepaid',
            'cod' => 'Cash on Delivery',
        ],
        'kinds' => [
            'code' => 'Code coupon',
            'auto' => 'Automatic offer',
            'seller' => 'Seller coupon',
            'personal' => 'Personal (referral)',
        ],
        'deal_states' => [
            'running' => 'Running',
            'scheduled' => 'Scheduled',
            'ended' => 'Ended',
            'disabled' => 'Disabled',
        ],
        'status' => [
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
        ],
    ],

    'settings' => [
        'group' => 'Promotions',
        'allow_stacking' => [
            'label' => 'Allow a coupon and an automatic offer together',
            'comment' => 'Only when both are marked stackable. Otherwise an applied coupon replaces the automatic offer.',
        ],
        'max_percent' => [
            'label' => 'Maximum discount (% of cart)',
            'comment' => 'All coupons and offers together never take more than this share of the cart subtotal. Default 90.',
        ],
        'first_order_prepaid_only' => [
            'label' => 'First-order coupons need online payment',
            'comment' => 'Blocks first-order coupons on Cash on Delivery (limits abuse through new accounts).',
        ],
        'referral_enabled' => [
            'label' => 'Referral rewards',
            'comment' => 'Customers share a link; when a friend\'s first order is delivered the customer gets a reward coupon.',
        ],
        'referral_reward_amount' => [
            'label' => 'Referral reward (₹)',
            'comment' => 'Flat value of the reward coupon. Default 100.',
        ],
        'referral_reward_min_cart' => [
            'label' => 'Reward coupon minimum order (₹)',
            'comment' => 'Default 299.',
        ],
        'referral_reward_validity_days' => [
            'label' => 'Reward coupon validity (days)',
            'comment' => 'Default 30.',
        ],
    ],
];
