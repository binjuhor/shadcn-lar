<?php

namespace Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Blog\Database\Factories\PostFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
        'meta_title',
        'meta_description',
        'category_id',
        'user_id',
        'views_count',
        'reading_time',
        'is_featured',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'views_count' => 'integer',
        'reading_time' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'featured_image_url',
    ];

    /**
     * Get the featured image URL.
     */
    public function getFeaturedImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured_image');

        return $media ? $media->getUrl() : null;
    }

    /**
     * Get the featured image thumbnail URL.
     */
    public function getFeaturedImageThumbnailAttribute(): ?string
    {
        $media = $this->getFirstMedia('featured_image');

        return $media ? $media->getUrl('thumb') : null;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }

    /**
     * Get the category that owns the post.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the user who created the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Get the tags for the post.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }

    /**
     * Scope for published posts.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    /**
     * Scope for featured posts.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = self::generateUniqueSlug($post->title);
            }

            if (empty($post->reading_time)) {
                $post->reading_time = self::calculateReadingTime($post->content);
            }
        });

        static::updating(function ($post) {
            if ($post->isDirty('slug') && ! empty($post->slug)) {
                $post->slug = self::generateUniqueSlug($post->slug, $post->id);
            }

            if ($post->isDirty('content')) {
                $post->reading_time = self::calculateReadingTime($post->content);
            }
        });
    }

    /**
     * Generate a unique slug.
     */
    private static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = \Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $originalSlug.'-'.$count;
            $count++;
        }

        return $slug;
    }

    /**
     * Check if slug exists.
     */
    private static function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = self::where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    /**
     * Calculate estimated reading time in minutes.
     */
    private static function calculateReadingTime(string $content): int
    {
        $wordCount = str_word_count(strip_tags($content));

        return max(1, ceil($wordCount / 200)); // Assuming 200 words per minute
    }
}
