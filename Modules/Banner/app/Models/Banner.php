<?php

namespace Modules\Banner\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;

class Banner extends Model
{
    use Translatable;

    protected $table = 'banner';

    public $translatedAttributes = ['title', 'content'];

    protected $fillable = [
        'group_id',
        'image',
        'code',
        'url',
        'is_featured',
        'sort_order',
        'status',
    ];

    // Please Don't remove below Line
    // AppendFunctionHere
    /**
     * relationship with Modules\Banner\Models\BannerGroup
     */
    public function bannerGroups()
    {
        return $this->belongsTo('Modules\Banner\Models\BannerGroup', 'group_id');
    }
}
