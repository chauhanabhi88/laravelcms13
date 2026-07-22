<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogCategory extends Model
{
    use Translatable, SoftDeletes;
    
    protected $table = 'blog_category';
    protected $translationForeignKey = 'blog_category_id';
    protected $fillable = ['slug','sort_order','status'];
    public $translatedAttributes = ['title','description','meta_keywords','meta_description'];
}
