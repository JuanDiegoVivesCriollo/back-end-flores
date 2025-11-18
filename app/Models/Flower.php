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

    /**
     * Boot the model
     * TEMPORARILY DISABLED - slug generation moved to controller for production stability
     */
    protected static function boot()
    {
        parent::boot();

        // Eventos boot() temporalmente deshabilitados para resolver errores de producción
        // La generación de slug ahora se maneja directamente en el controller

        /*
        static::creating(function ($flower) {
            if (empty($flower->slug)) {
                $flower->slug = Str::slug($flower->name);

                // Ensure slug is unique
                $originalSlug = $flower->slug;
                $counter = 1;
                while (static::where('slug', $flower->slug)->exists()) {
                    $flower->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });

        static::updating(function ($flower) {
            if ($flower->isDirty('name') && empty($flower->slug)) {
                $flower->slug = Str::slug($flower->name);

                // Ensure slug is unique
                $originalSlug = $flower->slug;
                $counter = 1;
                while (static::where('slug', $flower->slug)->where('id', '!=', $flower->id)->exists()) {
                    $flower->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });
        */
    }

    /**
     * Category relationship (single - for backward compatibility)
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Categories relationship (multiple)
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
     * Get effective price (discounted price if on sale, otherwise regular price)
     */
    public function getEffectivePriceAttribute()
    {
        if ($this->is_on_sale && $this->discount_percentage > 0) {
            return $this->price * (1 - $this->discount_percentage / 100);
        }
        return $this->price;
    }

    /**
     * Get discount percentage if on sale
     */
    public function getDiscountPercentageAttribute()
    {
        return $this->attributes['discount_percentage'] ?? 0;
    }

    /**
     * Check if flower is in stock
     */
    public function isInStock()
    {
        return $this->stock > 0;
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
     * Scope for in stock flowers
     */
    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Scope for ordered flowers (by sort_order)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }

    /**
     * Accessor simple para obtener la primera imagen (igual que Complement)
     */
    public function getFirstImageAttribute()
    {
        // Devolver la primera imagen del array de imágenes, igual que Complement
        if (is_array($this->images) && !empty($this->images)) {
            return $this->images[0];
        }
        return null; // Retornar null en lugar de placeholder
    }

    /**
     * Accessor simple para URLs de imágenes (igual que Complement)
     */
    public function getImageUrlsAttribute()
    {
        // Devolver las imágenes tal como están, igual que Complement
        if (is_array($this->images) && !empty($this->images)) {
            return $this->images;
        }
        return [];
    }
}
