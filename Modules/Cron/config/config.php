<?php

return [
    'name' => 'Cron',
    'cache' => [
		'entity_corn_schedule' => 'CronSchedule',
		'name' => 'Cron',
	],
    'cron_schedule_status_pending' => 1,
    'cron_schedule_status_success' => 2,
    'cron_schedule_status_fail' => 3,
    'lang_path' => 'cron::cron.labels',
];
