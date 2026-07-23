<?php

namespace Modules\Pages\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PagesTranslation extends Model
{
    use SoftDeletes;

    protected $table = 'pages_translation';

    protected $fillable = ['title', 'body', 'meta_title', 'meta_description'];

    // Please Don't remove below Line
    // AppendFunctionHere

}
