<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FlowerType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($flowerType) {
            if (empty($flowerType->slug)) {
                $flowerType->slug = Str::slug($flowerType->name);
            }
        });
    }

    /**
     * Flores que tienen este tipo
     */
    public function flowers()
    {
        return $this->belongsToMany(Flower::class, 'flower_flower_type');
    }

    /**
     * Scope para tipos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
