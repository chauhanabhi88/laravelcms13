<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryCurrencySetup extends Model
{

    protected $table = 'directory_currency_setup';

    protected $fillable = ['currency_id','label','symbol','is_base_currency'];

    //Please Don't remove below Line
	//AppendFunctionHere
}
