<?php

return [
    "cron::cron.settings.cron" => [
        'cron_schedule_delete_time' => [
            'label' => 'cron::cron.settings.cron_schedule.label',
            'type' => 'number',
            'placeholder' => 'cron::cron.settings.cron_schedule.label',
            'storage' => 'db',
            'comment' => 'cron::cron.settings.cron_schedule.comment',
            'default' => '1440',
            'min' => 0,
            'max' => 10080,
        ],
    ]
]

?>