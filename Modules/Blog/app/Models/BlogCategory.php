<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Blog\Database\Factories\BlogCategoryFactory;
use Modules\Core\Repositories\Transaltion\Translatable;

class BlogCategory extends Model
{
    use HasFactory, SoftDeletes, Translatable;

    protected $table = 'blog_category';

    protected $translationForeignKey = 'blog_category_id';

    protected $fillable = ['slug', 'sort_order', 'status'];

    public $translatedAttributes = ['title', 'description', 'meta_keywords', 'meta_description'];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_category', 'category_id', 'post_id');
    }

    protected static function newFactory(): BlogCategoryFactory
    {
        return BlogCategoryFactory::new();
    }
}
