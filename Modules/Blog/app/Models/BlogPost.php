<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Blog\Database\Factories\BlogPostFactory;
use Modules\Core\Repositories\Transaltion\Translatable;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes, Translatable;

    protected $table = 'blog_post';

    protected $translationForeignKey = 'blog_post_id';

    protected $fillable = ['slug', 'image', 'author', 'post_date', 'is_featured', 'status'];

    public $translatedAttributes = ['title', 'short_content', 'content', 'meta_keywords', 'meta_description'];

    protected $casts = [
        'post_date' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_post_category', 'post_id', 'category_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogPostComment::class, 'post_id');
    }

    /**
     * Enabled posts whose post date has arrived (or is unset).
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), config('core.enabled'))
            ->where(function (Builder $query) {
                $query->whereNull($this->qualifyColumn('post_date'))
                    ->orWhere($this->qualifyColumn('post_date'), '<=', now());
            });
    }

    public function isFeatured(): bool
    {
        return (int) $this->is_featured === (int) config('core.yes');
    }

    /**
     * Public URL of the cover image, optionally resized/cropped; null when there is none.
     */
    public function imageUrl(?int $width = null, ?int $height = null): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        $params = ['module' => config('blog.name').'/'.config('blog.blog_post_name'), 'image' => $this->image];

        if ($width || $height) {
            $params += ['image-type' => 'resize', 'width' => $width, 'height' => $height, 'crop-image' => $width && $height];
        }

        return getImageUrl($params) ?? getImageUrl(['module' => $params['module'], 'image' => $this->image]);
    }

    /**
     * Short teaser: the admin-entered short content, else the stripped body.
     */
    public function excerpt(int $length = 160): string
    {
        $source = $this->short_content ?: $this->content;

        $text = html_entity_decode(strip_tags((string) $source), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        /* Collapse whitespace, including the &nbsp; / zero-width chars rich-text editors leave behind */
        return Str::limit(trim(preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', ' ', $text)), $length);
    }

    /**
     * Estimated reading time in minutes (~200 wpm).
     */
    public function readingTime(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->content)) / 200));
    }

    protected static function newFactory(): BlogPostFactory
    {
        return BlogPostFactory::new();
    }
}
