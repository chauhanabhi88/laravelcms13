<?php

namespace Modules\Cron\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Modules\Cron\Repositories\CronRepository;
use Modules\Cron\Repositories\CronScheduleRepository;
use Modules\Cron\Models\CronSchedule;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
	
	
	protected $commands = [
		\Modules\Attribute\Console\TestCommand::class,
		\Modules\Banner\Console\Make::class,
		\Modules\Mail\Console\Clearlogs::class,
		\Modules\Core\Console\Logdelete::class,
		\Modules\Core\Console\Deletetempimage::class,
		\Modules\Cron\Console\Test::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        try {
            $cronRepository = app(CronRepository::class);
            $attributes = [
                "status" => config("core.enabled")
            ];
            $crons = $cronRepository->getByAttributes($attributes);
            if(count($crons)) {
                foreach ($crons as $cron) {
                    \Log::info("Cron command: ".$cron->command);
                    $cronExpression = "* * * * *";
                    if($cron->cron_expression) {
                        $cronExpression = $cron->cron_expression;
                    }
                    $schedule->command($cron->command)->cron($cronExpression)->before(function () use ($cron) {
                        $this->manageJobSchedule($cron);
                    })->onSuccess(function () use ($cron) {
                         \Log::info("Cron success: ".json_encode($cron));
                        $this->manageJobSchedule($cron, config("cron.cron_schedule_status_success"));
                    })
                    ->onFailure(function () use ($cron) {
                         \Log::info("Cron failed: ".json_encode($cron));
                        $this->manageJobSchedule($cron, config("cron.cron_schedule_status_fail"));
                    });
                    
                }
            }
        } catch (\Throwable $th) {
            \Log::info("Cron error : ".$th->getMessage());
            \Log::info($th->getTraceAsString());
        }
    }

    protected function manageJobSchedule($cron, $status = null)
    {
        $cronScheduleRepository = app(CronScheduleRepository::class);
        $current = date("Y-m-d H:i:s");
        $setting = settings("cron", "cron_schedule_delete_time");
        $cronScheduleEntity = new CronSchedule;
        $temp = DB::table($cronScheduleEntity->getTable())
                ->where('status',2)
                ->whereRaw('ABS(TIMESTAMPDIFF(MINUTE, finished_date, ?)) > '.$setting, [$current])
                ->delete();
        if($status) {
            $cronSchedule = $cronScheduleEntity->latest()->first();
            $cronScheduleRepository->update($cronSchedule, [
                'status' => $status,
                'finished_date' => date("Y-m-d H:i:s")
            ]);
        } else {
            $cronScheduleRepository->create([
                'cron_id' => $cron->id,
                'title'   => $cron->title,
                'command'   => $cron->command,
                'status'  => config("cron.cron_schedule_status_pending"),
                'execute_date' => date("Y-m-d H:i:s")
            ]);
        }
    }
}