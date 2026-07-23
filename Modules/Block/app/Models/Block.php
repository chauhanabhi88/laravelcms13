<?php

namespace Modules\Block\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Repositories\Transaltion\Translatable;

class Block extends Model
{
    use SoftDeletes, Translatable;

    protected $translationForeignKey = 'block_id';

    protected $table = 'cms_block';

    protected $fillable = ['slug', 'is_enabled'];

    public $translatedAttributes = ['title', 'content'];

    // Please Don't remove below Line
    // AppendFunctionHere

}
