<?php

/*
| Logistics settings (admin > Settings > Logistics). Read through
| Modules\Logistics\Support\LogisticsSettings, never with settings() directly; blank values fall
| back to config('logistics.defaults').
*/

return [
    'logistics::logistics.settings.group' => [
        'default_courier' => [
            'label' => 'logistics::logistics.settings.default_courier.label',
            'type' => 'text',
            'placeholder' => 'shiprocket',
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.default_courier.comment',
        ],
        'default_strategy' => [
            'label' => 'logistics::logistics.settings.default_strategy.label',
            'type' => 'select',
            'options' => [
                'recommended' => 'Recommended',
                'cheapest' => 'Cheapest',
                'fastest' => 'Fastest',
            ],
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.default_strategy.comment',
        ],
        'live_serviceability' => [
            'label' => 'logistics::logistics.settings.live_serviceability.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.live_serviceability.comment',
        ],
        'auto_rto_after_attempts' => [
            'label' => 'logistics::logistics.settings.auto_rto_after_attempts.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.auto_rto_after_attempts.comment',
        ],
        'poll_interval_hours' => [
            'label' => 'logistics::logistics.settings.poll_interval_hours.label',
            'type' => 'text',
            'placeholder' => '2',
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.poll_interval_hours.comment',
        ],
        'serviceability_cache_hours' => [
            'label' => 'logistics::logistics.settings.serviceability_cache_hours.label',
            'type' => 'text',
            'placeholder' => '24',
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.serviceability_cache_hours.comment',
        ],
        'estimator_timeout_ms' => [
            'label' => 'logistics::logistics.settings.estimator_timeout_ms.label',
            'type' => 'text',
            'placeholder' => '1500',
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.estimator_timeout_ms.comment',
        ],
        'rto_charge_percent' => [
            'label' => 'logistics::logistics.settings.rto_charge_percent.label',
            'type' => 'text',
            'placeholder' => '100',
            'storage' => 'db',
            'comment' => 'logistics::logistics.settings.rto_charge_percent.comment',
        ],
    ],
];
