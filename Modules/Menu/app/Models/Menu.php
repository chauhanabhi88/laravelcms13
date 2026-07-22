<?php

namespace Modules\Menu\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{


    protected $table = 'menu';

    protected $fillable = ['label', 'parent_id', 'link', 'link_target', 'css_class', 'icon', 'sort_order', 'is_system', 'status'];


    public function child()
    {
        return $this->hasMany("Modules\Menu\Models\Menu", "parent_id", "id")->where('status', config('core.enabled'))->orderBy('sort_order', 'ASC')->with('child');
    }
}
