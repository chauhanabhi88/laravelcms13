<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class DirectoryCountryCity extends Model
{ 
	use Translatable;
    protected $table = 'directory_country_city';

    protected $fillable = ['country','state'];
    public $translatedAttributes = ["name"];
    public $timestamps = true;

    //Please Don't remove below Line
	//AppendFunctionHere
}
