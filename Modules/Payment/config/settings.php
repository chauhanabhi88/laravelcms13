<?php

/*
| Payment settings (admin > Settings > Payment). Read through Modules\Payment\Support\PaymentSettings,
| never with settings() directly; blank values fall back to config('payment.defaults').
*/

return [
    'payment::payment.settings.group' => [
        'cod_enabled' => [
            'label' => 'payment::payment.settings.cod_enabled.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_enabled.comment',
        ],
        'cod_min_value' => [
            'label' => 'payment::payment.settings.cod_min_value.label',
            'type' => 'text',
            'placeholder' => '0',
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_min_value.comment',
        ],
        'cod_max_value' => [
            'label' => 'payment::payment.settings.cod_max_value.label',
            'type' => 'text',
            'placeholder' => '5000',
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_max_value.comment',
        ],
        'cod_daily_limit' => [
            'label' => 'payment::payment.settings.cod_daily_limit.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_daily_limit.comment',
        ],
        'cod_otp_threshold' => [
            'label' => 'payment::payment.settings.cod_otp_threshold.label',
            'type' => 'text',
            'placeholder' => '2000',
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_otp_threshold.comment',
        ],
        'cod_otp_new_customers' => [
            'label' => 'payment::payment.settings.cod_otp_new_customers.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_otp_new_customers.comment',
        ],
        'payment_window_minutes' => [
            'label' => 'payment::payment.settings.payment_window_minutes.label',
            'type' => 'text',
            'placeholder' => '30',
            'storage' => 'db',
            'comment' => 'payment::payment.settings.payment_window_minutes.comment',
        ],
        'cod_otp_window_minutes' => [
            'label' => 'payment::payment.settings.cod_otp_window_minutes.label',
            'type' => 'text',
            'placeholder' => '15',
            'storage' => 'db',
            'comment' => 'payment::payment.settings.cod_otp_window_minutes.comment',
        ],
    ],
];
