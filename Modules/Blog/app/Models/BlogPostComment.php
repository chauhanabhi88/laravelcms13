<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Blog\Database\Factories\BlogPostCommentFactory;
use Modules\Customer\Models\Customer;

class BlogPostComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'blog_post_comment';

    protected $fillable = ['post_id', 'admin_id', 'customer_id', 'comment', 'subject', 'status'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    protected static function newFactory(): BlogPostCommentFactory
    {
        return BlogPostCommentFactory::new();
    }
}
