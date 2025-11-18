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
        'shipping_cost',
        'is_active',
        'zone',
        'notes'
    ];

    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active districts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by zone
     */
    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    /**
     * Get districts grouped by zone
     */
    public static function getByZone()
    {
        return self::active()
                   ->orderBy('zone')
                   ->orderBy('name')
                   ->get()
                   ->groupBy('zone');
    }

    /**
     * Get shipping cost for district name
     */
    public static function getShippingCost($districtName)
    {
        $district = self::active()
                       ->where('name', $districtName)
                       ->orWhere('slug', \Illuminate\Support\Str::slug($districtName))
                       ->first();

        return $district ? $district->shipping_cost : 0;
    }

    /**
     * Check if district exists and is active
     */
    public static function isAvailable($districtName)
    {
        return self::active()
                  ->where('name', $districtName)
                  ->orWhere('slug', \Illuminate\Support\Str::slug($districtName))
                  ->exists();
    }
}
