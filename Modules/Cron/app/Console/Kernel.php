<?php

namespace Modules\Cron\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Modules\Attribute\Console\TestCommand;
use Modules\Banner\Console\Make;
use Modules\Core\Console\Deletetempimage;
use Modules\Core\Console\Logdelete;
use Modules\Cron\Models\CronSchedule;
use Modules\Cron\Repositories\CronRepository;
use Modules\Cron\Repositories\CronScheduleRepository;
use Modules\Mail\Console\Clearlogs;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        TestCommand::class,
        Make::class,
        Clearlogs::class,
        Logdelete::class,
        Deletetempimage::class,
    ];

    /**
     * Pending cron_schedules row id for the current run, keyed by cron id.
     *
     * Populated in the scheduled task's before() callback so the matching
     * onSuccess()/onFailure() callback updates the correct schedule row.
     *
     * @var array
     */
    protected $pendingScheduleIds = [];

    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        try {
            $cronRepository = app(CronRepository::class);
            $attributes = [
                'status' => config('core.enabled'),
            ];
            $crons = $cronRepository->getByAttributes($attributes);
            foreach ($crons as $cron) {
                $cronExpression = $cron->cron_expression ?: '* * * * *';
                $schedule->command($cron->command)->cron($cronExpression)->before(function () use ($cron) {
                    $this->manageJobSchedule($cron);
                })->onSuccess(function () use ($cron) {
                    $this->manageJobSchedule($cron, config('cron.cron_schedule_status_success'));
                })->onFailure(function () use ($cron) {
                    $this->manageJobSchedule($cron, config('cron.cron_schedule_status_fail'));
                });
            }
        } catch (\Throwable $th) {
            \Log::error('Cron schedule error: '.$th->getMessage());
            \Log::error($th->getTraceAsString());
        }
    }

    protected function manageJobSchedule($cron, $status = null)
    {
        $cronScheduleRepository = app(CronScheduleRepository::class);

        if ($status) {
            $scheduleId = $this->pendingScheduleIds[$cron->id] ?? null;
            $cronSchedule = $scheduleId ? $cronScheduleRepository->find($scheduleId) : null;
            if ($cronSchedule) {
                $cronScheduleRepository->update($cronSchedule, [
                    'status' => $status,
                    'finished_date' => date('Y-m-d H:i:s'),
                ]);
                unset($this->pendingScheduleIds[$cron->id]);
            }

            return;
        }

        $this->pruneOldSchedules();

        $cronSchedule = $cronScheduleRepository->create([
            'cron_id' => $cron->id,
            'title' => $cron->title,
            'command' => $cron->command,
            'status' => config('cron.cron_schedule_status_pending'),
            'execute_date' => date('Y-m-d H:i:s'),
        ]);
        $this->pendingScheduleIds[$cron->id] = $cronSchedule->id;
    }

    /**
     * Delete finished (success/fail) schedule rows older than the configured retention.
     *
     * @return void
     */
    protected function pruneOldSchedules()
    {
        $current = date('Y-m-d H:i:s');
        $setting = (int) settings('cron', 'cron_schedule_delete_time');
        $cronScheduleEntity = new CronSchedule;

        DB::table($cronScheduleEntity->getTable())
            ->whereIn('status', [
                config('cron.cron_schedule_status_success'),
                config('cron.cron_schedule_status_fail'),
            ])
            ->whereNotNull('finished_date')
            ->whereRaw('ABS(TIMESTAMPDIFF(MINUTE, finished_date, ?)) > ?', [$current, $setting])
            ->delete();
    }
}
