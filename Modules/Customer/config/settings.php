<?php
$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));

return [
    "customer::customer.settings.customer" => [
        'max_upload_size' => [
            'label' => 'customer::customer.settings.max_upload.label',
            'type' => 'number',
            'placeholder' => 'customer::customer.settings.max_upload.label',
            'storage' => 'db',
            'comment' => 'customer::customer.settings.max_upload.comment',
            'max' => $maxUploadServer,
            'min' => 0,
        ],
        'image_type' => [
            'label' => 'customer::customer.settings.image_type.label',
            'type' => 'text',
            'placeholder' => 'customer::customer.settings.image_type.label',
            'storage' => 'db',
            'comment' => 'customer::customer.settings.image_type.comment'
        ],
        'min_password_length' => [
            'label' => 'customer::customer.settings.min_password_length.label',
            'type' => 'number',
            'placeholder' => 'customer::customer.settings.min_password_length.label',
            'comment' => 'customer::customer.settings.min_password_length.comment',
            'storage' => 'db',
            'max' => 20,
            'min' => 0,
        ],
        'max_password_length' => [
            'label' => 'customer::customer.settings.max_password_length.label',
            'type' => 'number',
            'placeholder' => 'customer::customer.settings.max_password_length.label',
            'comment' => 'customer::customer.settings.max_password_length.comment',
            'storage' => 'db',
            'default' => 20,
            'max' => 20,
            'min' => 0,
        ],
        'ajax_call_after_seconds' => [
            'label' => 'customer::customer.settings.ajax_call_after_seconds.label',
            'type' => 'number',
            'placeholder' => 'customer::customer.settings.ajax_call_after_seconds.label',
            'comment' => 'customer::customer.settings.ajax_call_after_seconds.comment',
            'storage' => 'db',
            'max' => 1000,
            'min' => 0,
        ],
        'email_verification' => [
            'label' => 'customer::customer.settings.email_verification_after_registration',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db'
        ],
        'phone_verification' => [
            'label' => 'customer::customer.settings.phone_verification_after_registration',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db'
        ],
        'multi_device_login' => [
            'label' => 'customer::customer.settings.multi_device_login',
            'type' => 'select',
            'options' => getStatusOption(),
            'storage' => 'db'
        ],
    ]
]

?>