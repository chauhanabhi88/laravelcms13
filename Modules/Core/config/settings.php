<?php


return [
    'core::core.settings.general' => [
        'per_page' => [
            'label' => 'core::core.settings.per_page',
            'comment' => 'core::core.comment.per_page',
            'type' => 'text',
            'default' => '20',
            'placeholder' => 'core::core.settings.per_page',
            'storage' => 'db'
        ],
        'password' => [
            'label' => 'core::core.settings.mail_password',
            'type' => 'password',
            'placeholder' => 'core::core.settings.mail_password',
            'storage' => 'db'
        ],
        'admin_password' => [
            'label' => 'Admin Passwod',
            'type' => 'password',
            'placeholder' => 'core::core.settings.mail_password',
            'storage' => 'db'
        ],
        'default_per_page' => [
            'label' => 'core::core.settings.default_per_page',
            'type' => 'select',
            'options' => getPerPageOption(),
            'storage' => 'db'
        ],
        'timezone' => [
            'label' => 'core::core.settings.timezone',
            'type' => 'select',
            'options' => getTimezoneList(),
            'storage' => 'db'
        ],
        'session.lifetime' => [
            'env_key' => 'SESSION_LIFETIME',
            'label' => 'core::core.settings.session_lifetime',
            'type' => 'text',
            'placeholder' => 'core::core.settings.session_lifetime',
            'storage' => 'env'
        ],
        'per_page_front_pagination' => [
            'label' => 'core::core.settings.per_page_front_pagination',
            'type' => 'text',
            'default' => '2',
            'placeholder' => 'core::core.settings.per_page_front_pagination',
            'storage' => 'db'
        ],
        'import_translation_type' => [
            'label' => 'core::core.settings.import_file_type.import_label',
            'type' => 'text',
            'placeholder' => 'core::core.settings.import_file_type.import_label',
            'storage' => 'db',
            'comment' => 'core::core.settings.import_file_type.import_comment'
        ],
        'escape_html_ignore_column' => [
            'label' => 'core::core.settings.escape_html_ignore_column',
            'comment' => 'core::core.comment.escape_html_ignore_column',
            'type' => 'text',
            'placeholder' => 'core::core.settings.escape_html_ignore_column',
            'storage' => 'db'
        ],
        'max_delete_limit' => [
            'label' => 'core::core.settings.max_delete_limit',
            'type' => 'number',
            'default' => '1000',
            'placeholder' => 'core::core.settings.max_delete_limit',
            'storage' => 'db'
        ],
        'maintenance_mode_secret' => [
            'label' => 'core::core.settings.maintenance_mode_secret',
            'type' => 'text',
            'placeholder' => 'core::core.settings.maintenance_mode_secret',
            'storage' => 'db'
        ],
        'maintenance_mode_message' => [
            'label' => 'core::core.settings.maintenance_mode_message',
            'type' => 'text',
            'placeholder' => 'core::core.settings.maintenance_mode_message',
            'storage' => 'db'
        ],
        'view_password' => [
            'label' => 'core::core.settings.view_password',
            'type' => 'select',
            'options' => viewPasswordOption(),
            'storage' => 'db',
            'default' => config('core.on'),
        ],
    ],
    'core::core.settings.google_map' => [
        'show_google_map' => [
            'label' => 'core::core.settings.show_google_map',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db'
        ],
        'google_map_key' => [
            'label' => 'core::core.settings.google_map_key',
            'type' => 'text',
            'placeholder' => 'core::core.settings.google_map_key',
            'storage' => 'db'
        ],
    ],
    // 'core::core.settings.email_verification' => [
    //     'email_verification' => [
    //         'label' => 'core::core.settings.email_verification_after_registration',
    //         'type' => 'select',
    //         'options' => getStatusOption(),
    //         'storage' => 'db'
    //     ],
    // ]
];
