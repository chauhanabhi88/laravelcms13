<?php

$maxUploadServer = (int) (ini_get('upload_max_filesize')) > (int) (ini_get('post_max_size')) ? (int) (ini_get('post_max_size')) : (int) (ini_get('upload_max_filesize'));

return [
    'directory::country.settings.country' => [

        'import_country_type' => [
            'label' => 'core::core.settings.import_file_type.import_label',
            'type' => 'text',
            'placeholder' => 'core::core.settings.import_file_type.import_label',
            'storage' => 'db',
            'comment' => 'core::core.settings.import_file_type.import_comment',
        ],
        'max_upload_size' => [
            'label' => 'directory::country.settings.max_upload.label',
            'type' => 'number',
            'placeholder' => 'directory::country.settings.max_upload.label',
            'storage' => 'db',
            'comment' => 'directory::country.settings.max_upload.comment',
            'max' => $maxUploadServer,
            'min' => 0,
        ],
        'default_country' => [
            'label' => 'directory::directory_pincode.settings.default_country.label',
            'type' => 'text',
            'placeholder' => 'IN',
            'storage' => 'db',
            'comment' => 'directory::directory_pincode.settings.default_country.comment',
        ],
    ],

];
