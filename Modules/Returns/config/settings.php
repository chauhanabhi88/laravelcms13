<?php

/*
| Returns settings (admin > Settings > Returns). Read through Modules\Returns\Support\ReturnSettings,
| never with settings() directly; blank values fall back to config('returns.defaults').
*/

return [
    'returns::returns.settings.group' => [
        'auto_approve' => [
            'label' => 'returns::returns.settings.auto_approve.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'returns::returns.settings.auto_approve.comment',
        ],
        'auto_approve_max_return_rate' => [
            'label' => 'returns::returns.settings.auto_approve_max_return_rate.label',
            'type' => 'text',
            'placeholder' => '30',
            'storage' => 'db',
            'comment' => 'returns::returns.settings.auto_approve_max_return_rate.comment',
        ],
        'auto_approve_max_value' => [
            'label' => 'returns::returns.settings.auto_approve_max_value.label',
            'type' => 'text',
            'placeholder' => '2000',
            'storage' => 'db',
            'comment' => 'returns::returns.settings.auto_approve_max_value.comment',
        ],
        'refund_on_pickup_for_trusted' => [
            'label' => 'returns::returns.settings.refund_on_pickup_for_trusted.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'returns::returns.settings.refund_on_pickup_for_trusted.comment',
        ],
        'trusted_min_delivered_orders' => [
            'label' => 'returns::returns.settings.trusted_min_delivered_orders.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'returns::returns.settings.trusted_min_delivered_orders.comment',
        ],
        'max_images' => [
            'label' => 'returns::returns.settings.max_images.label',
            'type' => 'text',
            'placeholder' => '5',
            'storage' => 'db',
            'comment' => 'returns::returns.settings.max_images.comment',
        ],
        'claim_window_hours' => [
            'label' => 'returns::returns.settings.claim_window_hours.label',
            'type' => 'text',
            'placeholder' => '48',
            'storage' => 'db',
            'comment' => 'returns::returns.settings.claim_window_hours.comment',
        ],
        'refund_delivery_fee_on_seller_fault' => [
            'label' => 'returns::returns.settings.refund_delivery_fee_on_seller_fault.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'returns::returns.settings.refund_delivery_fee_on_seller_fault.comment',
        ],
    ],
];
