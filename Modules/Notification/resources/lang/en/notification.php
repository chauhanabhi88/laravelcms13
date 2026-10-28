<?php

return [
    'titles' => [
        'notifications' => 'Notifications',
    ],
    'channels' => [
        'mail' => 'Email',
        'sms' => 'SMS',
        'push' => 'Push',
        'database' => 'In-app',
        'whatsapp' => 'WhatsApp',
    ],
    'audiences' => [
        'customer' => 'Customer',
        'seller' => 'Seller',
        'admin' => 'Admin',
    ],
    'categories' => [
        'transactional' => 'Transactional',
        'promotional' => 'Promotional',
        'order_alerts' => 'Order alerts',
        'digest' => 'Daily digests',
    ],
    'settings' => [
        'group' => 'Notifications',
        'enable_sms_transactional' => [
            'label' => 'Transactional SMS',
            'comment' => 'Send order, return and account SMS through the hub. OTP SMS are not affected.',
        ],
        'enable_push' => [
            'label' => 'Push notifications',
            'comment' => 'Send push notifications to registered app devices.',
        ],
        'quiet_hours' => [
            'label' => 'Quiet hours',
            'placeholder' => '22:00-08:00',
            'comment' => 'Promotional messages that fall in this window wait until it ends. Leave blank to use 22:00-08:00; enter "off" to send at any time.',
        ],
    ],
];
