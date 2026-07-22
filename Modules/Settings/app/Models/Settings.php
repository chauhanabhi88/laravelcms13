<?php

namespace Modules\Settings\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $fillable = ['name', 'value'];

    protected $table = "settings";

    //Please Don't remove below Line
	//AppendFunctionHere
}
