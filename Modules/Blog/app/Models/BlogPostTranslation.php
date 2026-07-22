<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;

class BlogPostTranslation extends Model
{
    protected $table = 'blog_post_translation';
    protected $fillable = ['blog_post_id','title','short_content','content','meta_keywords','meta_description'];
}
