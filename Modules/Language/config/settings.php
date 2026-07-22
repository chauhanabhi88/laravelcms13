<?php
$maxUploadServer = (int)(ini_get('upload_max_filesize')) > (int)(ini_get('post_max_size')) ? (int)(ini_get('post_max_size')) : (int)(ini_get('upload_max_filesize'));

return [
    "language::language.settings.language" => [

        'import_translation_type' => [
            'label' => 'language::language.settings.import_file_type.import_label',
            'type' => 'text',
            'placeholder' => 'language::language.settings.import_file_type.import_label',
            'storage' => 'db',
            'comment' => 'language::language.settings.import_file_type.import_comment'
        ],
        'max_upload_size' => [
            'label' => 'language::language.settings.max_upload.label',
            'type' => 'number',
            'placeholder' => 'language::language.settings.max_upload.label',
            'storage' => 'db',
            'comment' => 'language::language.settings.max_upload.comment',
            'max' => $maxUploadServer,
            'min' => 0,
        ]
    ],

]
?>
