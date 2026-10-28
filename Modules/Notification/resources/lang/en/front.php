<?php

return [
    'inbox' => [
        'title' => 'Notifications',
        'subtitle' => 'Updates about your orders, returns and account.',
        'seller_subtitle' => 'Updates about your orders, listings, payouts and account.',
        'empty_title' => 'No notifications yet',
        'empty_text' => 'Order and account updates will show up here.',
        'mark_all_read' => 'Mark all as read',
        'all_read' => 'All notifications marked as read.',
        'unread' => '{0} No unread notifications|{1} 1 unread|[2,*] :count unread',
        'open' => 'Open',
        'mark_read' => 'Mark as read',
        'settings' => 'Notification settings',
        'not_found' => 'Notification not found.',
    ],
    'preferences' => [
        'title' => 'Notification settings',
        'customer_subtitle' => 'Choose how we tell you about offers. Order, delivery, return and account updates are always sent.',
        'seller_subtitle' => 'Choose which optional alerts you receive. Payouts, listing decisions and account notices are always sent.',
        'menu' => 'Notifications',
        'save' => 'Save settings',
        'saved' => 'Notification settings saved.',
        'always_on' => 'Order and account updates are always on.',
        // toggles.<channel>.<category>
        'toggles' => [
            'push' => [
                'promotional' => ['label' => 'Offers on the app', 'help' => 'Deals, price drops and reminders as push notifications.'],
            ],
            'sms' => [
                'promotional' => ['label' => 'Offers by SMS', 'help' => 'Occasional deals and sale alerts by text message.'],
                'order_alerts' => ['label' => 'SMS for new orders', 'help' => 'A text message for every new order, besides the in-app alert.'],
            ],
            'mail' => [
                'promotional' => ['label' => 'Offers by email', 'help' => 'Curated picks, review reminders and sale alerts by email.'],
                'digest' => ['label' => 'Daily digest by email', 'help' => 'Low-stock and pending-action summaries by email.'],
            ],
            'whatsapp' => [
                'promotional' => ['label' => 'Offers on WhatsApp', 'help' => 'Deals and sale alerts on WhatsApp.'],
            ],
            'database' => [
                'digest' => ['label' => 'Daily digest in the panel', 'help' => 'Low-stock and pending-action summaries in your notifications.'],
            ],
        ],
    ],
    'device_tokens' => [
        'saved' => 'Device registered for notifications.',
        'removed' => 'Device removed.',
    ],
];
