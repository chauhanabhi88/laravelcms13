<?php


$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));

return [
    "attribute::attribute.settings.attribute" => [
        'max_upload_size' => [
            'label' => 'attribute::attribute.settings.max_upload.label',
            'type' => 'number',
            'placeholder' => 'attribute::attribute.settings.max_upload.label',
            'storage' => 'db',
            'comment' => 'attribute::attribute.settings.max_upload.comment',
            'max' => $maxUploadServer,
            'min' => 0,
        ],
        'image_type' => [
            'label' => 'attribute::attribute.settings.image_type.label',
            'type' => 'text',
            'placeholder' => 'attribute::attribute.settings.image_type.label',
            'storage' => 'db',
            'comment' => 'attribute::attribute.settings.image_type.comment'
        ]
    ]
]

?>