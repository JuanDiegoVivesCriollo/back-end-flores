<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Flower extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'original_price',
        'discount_percentage',
        'sku',
        'color',
        'occasion',
        'images',
        'rating',
        'reviews_count',
        'stock',
        'is_active',
        'is_featured',
        'is_on_sale',
        'views',
        'sort_order',
        'metadata'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_percentage' => 'integer',
        'rating' => 'decimal:2',
        'reviews_count' => 'integer',
        'stock' => 'integer',
        'images' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_on_sale' => 'boolean',
        'views' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($flower) {
            if (empty($flower->slug)) {
                $flower->slug = Str::slug($flower->name);
                $originalSlug = $flower->slug;
                $counter = 1;
                while (static::where('slug', $flower->slug)->exists()) {
                    $flower->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
            
            // Generar SKU automático si no existe
            if (empty($flower->sku)) {
                $flower->sku = 'FDJ-' . strtoupper(Str::random(8));
            }
        });
    }

    /**
     * Category relationship
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Multiple categories relationship
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'flower_categories')
                    ->withTimestamps();
    }

    /**
     * Order items relationship
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Tipos de flores (Rosa, Girasol, Tulipán, etc.)
     * Un ramo puede tener múltiples tipos de flores
     */
    public function flowerTypes()
    {
        return $this->belongsToMany(FlowerType::class, 'flower_flower_type');
    }

    /**
     * Scope for active flowers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for featured flowers
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for flowers on sale
     */
    public function scopeOnSale($query)
    {
        return $query->where('is_on_sale', true);
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope by color
     */
    public function scopeByColor($query, $color)
    {
        return $query->where('color', $color);
    }

    /**
     * Scope by occasion
     */
    public function scopeByOccasion($query, $occasion)
    {
        return $query->where('occasion', $occasion);
    }

    /**
     * Scope by price range
     */
    public function scopeByPriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImageAttribute()
    {
        if (!empty($this->images) && is_array($this->images)) {
            return $this->images[0] ?? null;
        }
        return null;
    }

    /**
     * Calculate discount
     */
    public function getDiscountAmountAttribute()
    {
        if ($this->original_price && $this->original_price > $this->price) {
            return $this->original_price - $this->price;
        }
        return 0;
    }

    /**
     * Check if has discount
     */
    public function getHasDiscountAttribute()
    {
        return $this->discount_percentage > 0 || 
               ($this->original_price && $this->original_price > $this->price);
    }

    /**
     * Increment view count
     */
    public function incrementViews()
    {
        $this->increment('views');
    }
}
