<?php

/*
| Order settings (admin > Settings > Order). Read through Modules\Order\Support\OrderSettings,
| never with settings() directly; blank values fall back to config('order.defaults').
*/

return [
    'order::order.settings.group' => [
        'rto_block_threshold' => [
            'label' => 'order::order.settings.rto_block_threshold.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'order::order.settings.rto_block_threshold.comment',
        ],
        'rto_window_days' => [
            'label' => 'order::order.settings.rto_window_days.label',
            'type' => 'text',
            'placeholder' => '90',
            'storage' => 'db',
            'comment' => 'order::order.settings.rto_window_days.comment',
        ],
        'max_open_cod_orders' => [
            'label' => 'order::order.settings.max_open_cod_orders.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'order::order.settings.max_open_cod_orders.comment',
        ],
        'reseller_max_markup_multiplier' => [
            'label' => 'order::order.settings.reseller_max_markup_multiplier.label',
            'type' => 'text',
            'placeholder' => '1.5',
            'storage' => 'db',
            'comment' => 'order::order.settings.reseller_max_markup_multiplier.comment',
        ],
        'allow_customer_cancel_after_packed' => [
            'label' => 'order::order.settings.allow_customer_cancel_after_packed.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'order::order.settings.allow_customer_cancel_after_packed.comment',
        ],
    ],
];
