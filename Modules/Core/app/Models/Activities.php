<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;

class Activities extends Model
{
	protected $table = 'activity_log';

    protected $fillable = [
    	'admin_id',
        'ip_address',
        'module',
        'action',
        'message'
    ];

    //Please Don't remove below Line
	//AppendFunctionHere
}
