<?php

namespace Modules\Pages\Models;

use Illuminate\Database\Eloquent\Model;

class PagesTranslation extends Model
{

    protected $table = 'pages_translation';
    protected $fillable = ['title','body','meta_title','meta_description'];
    
    //Please Don't remove below Line
	//AppendFunctionHere
    
}
