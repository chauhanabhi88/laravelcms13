<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryCurrencyRate extends Model
{

    protected $table = 'directory_currency_rate';

    protected $fillable = ['currency_from','currency_to','rate'];

    //Please Don't remove below Line
	//AppendFunctionHere
}
