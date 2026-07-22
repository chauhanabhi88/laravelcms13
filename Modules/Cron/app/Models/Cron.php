<?php

namespace Modules\Cron\Models;
use Illuminate\Database\Eloquent\Model;


class Cron extends Model
{
    protected $table = 'cron';
    protected $fillable = [
        'title',
        'command',
        'description',
        'cron_expression',
        'status'
    ];

    //Please Don't remove below Line
	//AppendFunctionHere
}
