<?php

namespace Modules\Language\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $table = 'language';

    protected $fillable = ['title', 'locale', 'is_default', 'status'];

}