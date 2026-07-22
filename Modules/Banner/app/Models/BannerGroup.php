<?php

namespace Modules\Banner\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class BannerGroup extends Model
{
    use Translatable;

    protected $table = 'banner_group';

    public $translatedAttributes = ['name'];

    protected $fillable = [
        'code',
        'status',
        'sort_order'
    ];

    //Please Don't remove below Line
    //AppendFunctionHere
    /**
    * relationship with Modules\Banner\Models\Banner
    *
    */
    public function banners()
    {
        return $this->hasMany('Modules\Banner\Models\Banner', 'group_id')->where('status', config("core.yes"));
    }
}