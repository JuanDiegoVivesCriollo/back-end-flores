<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryDistrict extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'zone',
        'shipping_cost',
        'estimated_time',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope for active districts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope ordered
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope by zone
     */
    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }
}
