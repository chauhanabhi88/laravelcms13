<?php

namespace Modules\Cron\Models;
use Illuminate\Database\Eloquent\Model;


class CronSchedule extends Model
{
    protected $table = 'cron_schedules';
    protected $fillable = [
        'cron_id',
        'title',
        'message',
        'command',
        'execute_date',
        'finished_date',
        'status'
    ];

    //Please Don't remove below Line
    //AppendFunctionHere
}
