<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryCountryStateTranslation extends Model
{
	protected $table = "directory_state_translation";
    protected $fillable = ["directory_country_state_id","name"];
    public $timestamps = true;

    //Please Don't remove below Line
    //AppendFunctionHere
}
