<?php

namespace Modules\Customer\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{    
    protected $table = 'customer_group';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_default'
    ];

    //Please Don't remove below Line
	//AppendFunctionHere
}