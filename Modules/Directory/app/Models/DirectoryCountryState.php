<?php

namespace Modules\Directory\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class DirectoryCountryState extends Model
{
    use Translatable;
    protected $table = 'directory_state';

    protected $fillable = ['country', 'code'];
    public $translatedAttributes = ["name"];
    public $timestamps = true;

    //Please Don't remove below Line
    //AppendFunctionHere
}