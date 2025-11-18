<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'image', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Flowers relationship (single - for backward compatibility)
     */
    public function flowers()
    {
        return $this->hasMany(Flower::class);
    }

    /**
     * Flowers relationship (multiple through pivot table)
     */
    public function flowersMultiple()
    {
        return $this->belongsToMany(Flower::class, 'flower_categories')
                    ->withTimestamps();
    }

    /**
     * Get active flowers in this category
     */
    public function activeFlowers()
    {
        return $this->flowers()->where('is_active', true);
    }

    /**
     * Get active flowers in this category (multiple relationship)
     */
    public function activeFlowersMultiple()
    {
        return $this->flowersMultiple()->where('is_active', true);
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered categories
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
