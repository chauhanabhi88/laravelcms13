<?php

/*
| Review settings (admin > Settings > Reviews). Read through Modules\Review\Support\ReviewSettings,
| never with settings() directly; blank values fall back to config('review.defaults').
*/

return [
    'review::review.settings.group' => [
        'auto_publish' => [
            'label' => 'review::review.settings.auto_publish.label',
            'type' => 'select',
            'options' => [
                1 => 'Yes',
                2 => 'No',
            ],
            'storage' => 'db',
            'comment' => 'review::review.settings.auto_publish.comment',
        ],
        'min_body_length' => [
            'label' => 'review::review.settings.min_body_length.label',
            'type' => 'text',
            'placeholder' => '10',
            'storage' => 'db',
            'comment' => 'review::review.settings.min_body_length.comment',
        ],
        'max_images' => [
            'label' => 'review::review.settings.max_images.label',
            'type' => 'text',
            'placeholder' => '5',
            'storage' => 'db',
            'comment' => 'review::review.settings.max_images.comment',
        ],
        'edit_window_days' => [
            'label' => 'review::review.settings.edit_window_days.label',
            'type' => 'text',
            'placeholder' => '30',
            'storage' => 'db',
            'comment' => 'review::review.settings.edit_window_days.comment',
        ],
        'report_threshold' => [
            'label' => 'review::review.settings.report_threshold.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'review::review.settings.report_threshold.comment',
        ],
        'prompt_delay_days' => [
            'label' => 'review::review.settings.prompt_delay_days.label',
            'type' => 'text',
            'placeholder' => '3',
            'storage' => 'db',
            'comment' => 'review::review.settings.prompt_delay_days.comment',
        ],
    ],
];
