<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryCountryCityTranslation extends Model
{
	protected $table = "directory_country_city_translation";
    protected $fillable = ["name"];
    public $timestamps = true;

    //Please Don't remove below Line
    //AppendFunctionHere
}
