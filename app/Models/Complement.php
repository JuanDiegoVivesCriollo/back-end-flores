<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'original_price',
        'discount_percentage',
        'type',
        'color',
        'size',
        'brand',
        'images',
        'rating',
        'reviews_count',
        'stock',
        'is_featured',
        'is_on_sale',
        'is_active',
        'views',
        'sort_order',
        'metadata'
    ];

    protected $casts = [
        'images' => 'array',
        'metadata' => 'array',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'rating' => 'decimal:2',
        'is_featured' => 'boolean',
        'is_on_sale' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image' // Agregar el accessor como atributo en las respuestas JSON
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnSale($query)
    {
        return $query->where('is_on_sale', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    // Accessors
    public function getImageAttribute()
    {
        // Devolver la primera imagen del array de imágenes
        if (is_array($this->images) && !empty($this->images)) {
            return $this->images[0];
        }
        return '/img/placeholder.jpg'; // Fallback por si no hay imágenes
    }

    public function getDiscountedPriceAttribute()
    {
        if ($this->discount_percentage > 0 && $this->original_price) {
            return $this->original_price * (1 - $this->discount_percentage / 100);
        }
        return $this->price;
    }

    public function getIsOnSaleAttribute($value)
    {
        return $value || ($this->discount_percentage > 0 && $this->original_price > $this->price);
    }

    // Mutators
    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = \Str::slug($value);
    }

    // Relations para futuras órdenes
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'complement_id');
    }
}
