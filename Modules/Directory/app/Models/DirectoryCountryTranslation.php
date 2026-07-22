<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryCountryTranslation extends Model
{

    protected $table = 'directory_country_translation';
    protected $fillable = ['directory_country_id','name'];
    public $timestamps = true;

    //Please Don't remove below Line
	//AppendFunctionHere
}
