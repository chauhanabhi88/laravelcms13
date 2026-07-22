<?php

return [
    'name' => 'Mail',
    'cache' => [
        'name' => 'Mail',
        'mail_log' => 'MailLog'
    ],
    'mail_name' => 'mail',
    'enable_mail_log' => 1,
    'mail_log_status' => [
        'success' => 1,
        'failed' => 2,
    ],
    'lang_path' => 'mail::mail.labels'
];
