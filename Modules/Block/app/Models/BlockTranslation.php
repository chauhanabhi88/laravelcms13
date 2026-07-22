<?php

namespace Modules\Block\Models;
use Illuminate\Database\Eloquent\Model;

class BlockTranslation extends Model
{
    protected $table = 'cms_block_translation';
    protected $fillable = ['title','content'];

    //Please Don't remove below Line
	//AppendFunctionHere
}
