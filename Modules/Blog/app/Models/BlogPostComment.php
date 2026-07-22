<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPostComment extends Model
{
    use SoftDeletes;
    
    protected $table = 'blog_post_comment';

    protected $fillable = ['post_id','admin_id','customer_id','comment','subject','status'];

}
