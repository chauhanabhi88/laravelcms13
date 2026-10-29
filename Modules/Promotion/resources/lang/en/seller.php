<?php

return [
    'menu' => [
        'section' => 'Promotions',
        'coupons' => 'Coupons',
    ],

    'title' => 'Coupons',
    'create_title' => 'New coupon',
    'edit_title' => 'Edit coupon',
    'hint' => 'Create coupons for your own products. You fund the discount: it is taken from your sale amount when the order is settled.',

    'buttons' => [
        'add' => 'New coupon',
        'save' => 'Save coupon',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'enable' => 'Enable',
        'disable' => 'Disable',
    ],

    'columns' => [
        'code' => 'Code',
        'benefit' => 'Discount',
        'applies_to' => 'Applies to',
        'validity' => 'Valid',
        'used' => 'Used',
        'status' => 'Status',
    ],

    'scope' => [
        'seller' => 'All my products',
        'product' => ':count chosen products',
    ],

    'status' => [
        'live' => 'Live',
        'scheduled' => 'Scheduled',
        'expired' => 'Expired',
        'disabled' => 'Disabled',
    ],

    'fields' => [
        'products' => 'Products',
        'products_hint' => 'Choose the products the coupon covers.',
    ],

    'empty_title' => 'No coupons yet',
    'empty_text' => 'Create a coupon to give buyers a reason to order from your shop.',

    'messages' => [
        'created' => 'Coupon created.',
        'updated' => 'Coupon updated.',
        'enabled' => 'Coupon enabled.',
        'disabled' => 'Coupon disabled.',
    ],

    'errors' => [
        'not_your_product' => 'Choose only your own products.',
    ],
];
