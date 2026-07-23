<?php

namespace Modules\Block\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlockTranslation extends Model
{
    use SoftDeletes;

    protected $table = 'cms_block_translation';

    protected $fillable = ['title', 'content'];

    // Please Don't remove below Line
    // AppendFunctionHere
}
