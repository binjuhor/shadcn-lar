<?php

namespace Modules\Ecommerce\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\{
    Factories\HasFactory,
    Model,
    Relations\BelongsTo,
    Relations\BelongsToMany,
    Relations\HasMany,
    SoftDeletes
};

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'content',
        'sku',
        'price',
        'sale_price',
        'cost',
        'stock_quantity',
        'low_stock_threshold',
        'track_inventory',
        'status',
        'featured_image',
        'images',
        'category_id',
        'user_id',
        'views_count',
        'sales_count',
        'is_featured',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_inventory' => 'boolean',
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'is_featured' => 'boolean',
        'images' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag', 'product_id', 'product_tag_id')
            ->withTimestamps();
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = \Str::slug($product->name);
            }

            if (empty($product->sku)) {
                $product->sku = 'PRD-'.strtoupper(\Str::random(8));
            }
        });

        static::updating(function ($product) {
            if ($product->isDirty('name') && empty($product->slug)) {
                $product->slug = \Str::slug($product->name);
            }
        });
    }

    public function getEffectivePriceAttribute(): float
    {
        return $this->sale_price ?? $this->price;
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (! $this->is_on_sale) {
            return null;
        }

        return round((($this->price - $this->sale_price) / $this->price) * 100, 2);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->track_inventory && $this->stock_quantity <= 0;
    }

    public function incrementViewsCount(): void
    {
        $this->increment('views_count');
    }

    public function decrementStock(int $quantity): void
    {
        if ($this->track_inventory) {
            $this->decrement('stock_quantity', $quantity);
        }
    }

    public function incrementStock(int $quantity): void
    {
        if ($this->track_inventory) {
            $this->increment('stock_quantity', $quantity);
        }
    }
}
