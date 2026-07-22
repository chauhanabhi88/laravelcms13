<?php

return [
    'core::core.settings.smtp' => [
        'mail.mailers.smtp.host' => [
            'env_key' => 'MAIL_HOST',
            'label' => 'core::core.settings.mail_host',
            'type' => 'text',
            'placeholder' => 'core::core.settings.mail_host',
            'storage' => 'env'
        ],
        'mail.mailers.smtp.port' => [
            'env_key' => 'MAIL_PORT',
            'label' => 'core::core.settings.mail_port',
            'type' => 'text',
            'placeholder' => 'core::core.settings.mail_port',
            'storage' => 'env'
        ],
        'mail.mailers.smtp.username' => [
            'env_key' => 'MAIL_USERNAME',
            'label' => 'core::core.settings.mail_username',
            'type' => 'text',
            'placeholder' => 'core::core.settings.mail_username',
            'storage' => 'env'
        ],
        'mail.mailers.smtp.password' => [
            'env_key' => 'MAIL_PASSWORD',
            'label' => 'core::core.settings.mail_password',
            'type' => 'password',
            'placeholder' => 'core::core.settings.mail_password',
            'storage' => 'env'
        ],
        'mail.mailers.smtp.encryption' => [
            'env_key' => 'MAIL_ENCRYPTION',
            'label' => 'core::core.settings.mail_encryption',
            'type' => 'text',
            'placeholder' => 'core::core.settings.mail_encryption',
            'storage' => 'env'
        ],
        'sender_name' => [
            'label' => 'core::core.settings.sender_name',
            'type' => 'text',
            'placeholder' => 'core::core.settings.sender_name',
            'storage' => 'db'
        ],
        'sender_email' => [
            'label' => 'core::core.settings.sender_email',
            'type' => 'text',
            'placeholder' => 'core::core.settings.sender_email',
            'storage' => 'db'
        ],
        'recipient_admin_email' => [
            'label' => 'core::core.settings.recipient_admin_email',
            'type' => 'text',
            'placeholder' => 'core::core.settings.recipient_admin_email',
            'storage' => 'db'
        ],
    ]
];