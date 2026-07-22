<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;

class BlogCategoryTranslation extends Model
{
    
    protected $table = 'blog_category_translation';
    protected $fillable = ['blog_category_id','title','description','meta_keywords','meta_description'];
}