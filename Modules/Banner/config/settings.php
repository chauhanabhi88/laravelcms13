<?php


$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));

return [
    "banner::banner.settings.banner" => [
        'max_upload_size' => [
            'label' => 'banner::banner.settings.max_upload.label',
            'type' => 'number',
            'placeholder' => 'banner::banner.settings.max_upload.label',
            'storage' => 'db',
            'comment' => 'banner::banner.settings.max_upload.comment',
            'max' => $maxUploadServer,
            'min' => 0,
        ],
        'image_type' => [
            'label' => 'banner::banner.settings.image_type.label',
            'type' => 'text',
            'placeholder' => 'banner::banner.settings.image_type.label',
            'storage' => 'db',
            'comment' => 'banner::banner.settings.image_type.comment'
        ]
    ]
]

?>