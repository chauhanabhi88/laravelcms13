<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerLoginLog extends Model
{
    
    
    protected $table = 'customer_login_log';

    protected $fillable = ['customer_id','action','ip_address'];

}
