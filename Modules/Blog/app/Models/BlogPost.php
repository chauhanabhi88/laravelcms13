<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Repositories\Transaltion\Translatable;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    use Translatable, SoftDeletes;

    protected $table = 'blog_post';
    protected $translationForeignKey = 'blog_post_id';
    protected $fillable = ['slug','image','author','post_date','is_featured','status'];
    public $translatedAttributes = ['title','short_content','content','meta_keywords','meta_description'];
}
