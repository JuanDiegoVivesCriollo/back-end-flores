<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'flower_id',
        'complement_id',
        'breakfast_id',
        'item_type',
        'name',
        'quantity',
        'price',
        'total',
        'options',
        'notes'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer',
        'options' => 'array',
    ];

    // Item types
    const TYPE_FLOWER = 'flower';
    const TYPE_COMPLEMENT = 'complement';
    const TYPE_BREAKFAST = 'breakfast';

    /**
     * Order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Flower relationship
     */
    public function flower()
    {
        return $this->belongsTo(Flower::class);
    }

    /**
     * Complement relationship
     */
    public function complement()
    {
        return $this->belongsTo(Complement::class);
    }

    /**
     * Breakfast relationship
     */
    public function breakfast()
    {
        return $this->belongsTo(Breakfast::class);
    }

    /**
     * Get the item (polymorphic)
     */
    public function getItemAttribute()
    {
        switch ($this->item_type) {
            case self::TYPE_FLOWER:
                return $this->flower;
            case self::TYPE_COMPLEMENT:
                return $this->complement;
            case self::TYPE_BREAKFAST:
                return $this->breakfast;
            default:
                return null;
        }
    }

    /**
     * Calculate total before save
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total = $item->price * $item->quantity;
        });
    }
}
