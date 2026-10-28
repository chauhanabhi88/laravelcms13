<?php

return [
    'notification::notification.settings.group' => [
        'enable_sms_transactional' => [
            'label' => 'notification::notification.settings.enable_sms_transactional.label',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db',
            'comment' => 'notification::notification.settings.enable_sms_transactional.comment',
        ],
        'enable_push' => [
            'label' => 'notification::notification.settings.enable_push.label',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db',
            'comment' => 'notification::notification.settings.enable_push.comment',
        ],
        'quiet_hours' => [
            'label' => 'notification::notification.settings.quiet_hours.label',
            'type' => 'text',
            'placeholder' => 'notification::notification.settings.quiet_hours.placeholder',
            'storage' => 'db',
            'comment' => 'notification::notification.settings.quiet_hours.comment',
        ],
    ],
];
