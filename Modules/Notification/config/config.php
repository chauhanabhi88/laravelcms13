<?php

return [
    'name' => 'Notification',

    /*
    | Session filter / cache entity keys of the admin grids.
    */
    'cache' => [
        'notification_template' => 'notification_template',
        'notification_log' => 'notification_log',
    ],

    /*
    | Queue for every hub notification. Run a worker with --queue=notifications,default.
    */
    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),

    /*
    | Delivery channels the hub knows. "mail" uses Mail module templates (MailTemplate slug
    | "<event key with dots as dashes>-<audience>"); the others use notification_template rows.
    */
    'channels' => ['mail', 'sms', 'push', 'database', 'whatsapp'],

    'audiences' => ['customer', 'seller', 'admin'],

    /*
    | Categories a recipient may switch off per channel. "transactional" is always sent.
    */
    'categories' => ['transactional', 'promotional', 'order_alerts', 'digest'],

    'opt_out_categories' => ['promotional', 'order_alerts', 'digest'],

    /*
    | Preference toggles shown on the customer and seller settings pages: [channel, category].
    */
    'preference_toggles' => [
        'customer' => [
            ['push', 'promotional'],
            ['sms', 'promotional'],
            ['mail', 'promotional'],
            ['whatsapp', 'promotional'],
        ],
        'seller' => [
            ['sms', 'order_alerts'],
            ['mail', 'digest'],
            ['database', 'digest'],
        ],
    ],

    /*
    | SMS bodies are cut to this many concatenated segments (2 GSM-7 segments = 306 chars).
    */
    'sms_max_segments' => 2,

    /*
    | Promotional messages that fall inside quiet hours are delayed until they end.
    | Settings > Notification "quiet_hours" overrides this ("HH:MM-HH:MM", blank = off).
    */
    'quiet_hours' => '22:00-08:00',

    /*
    | In-app bell: unread counts are cached this many seconds; the dropdown shows this many.
    */
    'bell' => [
        'cache_seconds' => 60,
        'dropdown_limit' => 10,
        'poll_seconds' => 60,
        'per_page' => 20,
    ],

    /*
    | Push: "log" writes to storage/logs/notifications.log; "fcm" uses the FCM HTTP v1 API
    | with a service account JSON file. The OAuth token is cached for 50 minutes.
    */
    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            'credentials_path' => env('FCM_CREDENTIALS_PATH'),
            'token_uri' => 'https://oauth2.googleapis.com/token',
            'send_endpoint' => 'https://fcm.googleapis.com/v1/projects/{project}/messages:send',
            'token_cache_seconds' => 3000,
        ],
    ],

    /*
    | WhatsApp: only the "null" driver (logs the message) exists until a provider is chosen.
    */
    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'null'),
    ],

    /*
    | Device tokens accepted by POST /api/v1/customer/device-tokens.
    */
    'device_types' => ['android', 'ios', 'web'],

    'max_device_tokens' => 10,
];
