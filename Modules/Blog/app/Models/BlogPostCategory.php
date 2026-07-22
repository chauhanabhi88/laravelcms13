<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPostCategory extends Model
{
    protected $table = 'blog_post_category';
    protected $fillable = ['post id','category id'];
}
