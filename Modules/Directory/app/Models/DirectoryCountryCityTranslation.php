<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryCountryCityTranslation extends Model
{
    protected $table = 'directory_country_city_translation';

    protected $fillable = ['directory_country_city_id', 'name'];

    public $timestamps = true;

    // Please Don't remove below Line
    // AppendFunctionHere
}
