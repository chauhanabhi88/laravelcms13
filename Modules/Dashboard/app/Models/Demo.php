<?php

namespace Modules\Dashboard\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Demo extends Model
{
    
    
    protected $table = 'demo';

    protected $fillable = ['demo_field'];

}
