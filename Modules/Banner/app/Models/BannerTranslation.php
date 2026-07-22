<?php

namespace Modules\Banner\Models;

use Illuminate\Database\Eloquent\Model;

class BannerTranslation extends Model
{
    protected $table = 'banner_translation';

    protected $fillable = ['title','content'];

    //Please Don't remove below Line
    //AppendFunctionHere
}
