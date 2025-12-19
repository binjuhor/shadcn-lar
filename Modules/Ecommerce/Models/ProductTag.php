<?php

namespace Modules\Ecommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductTag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_tag', 'product_tag_id', 'product_id')
            ->withTimestamps();
    }

    public function activeProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_tag', 'product_tag_id', 'product_id')
            ->where('status', 'active')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = \Str::slug($tag->name);
            }
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name') && empty($tag->slug)) {
                $tag->slug = \Str::slug($tag->name);
            }
        });
    }

    public function getProductsCountAttribute(): int
    {
        return $this->products()->count();
    }

    public function getActiveProductsCountAttribute(): int
    {
        return $this->activeProducts()->count();
    }
}