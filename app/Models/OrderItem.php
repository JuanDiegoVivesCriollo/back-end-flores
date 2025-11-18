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
        'item_type',
        'flower_name',
        'quantity',
        'price',
        'total'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'quantity' => 'integer'
    ];

    /**
     * Get the order that owns the order item
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the flower for this order item
     */
    public function flower()
    {
        return $this->belongsTo(Flower::class);
    }

    /**
     * Get the complement for this order item
     */
    public function complement()
    {
        return $this->belongsTo(Complement::class);
    }

    /**
     * Get the product (flower or complement) for this order item
     */
    public function getProductAttribute()
    {
        if ($this->item_type === 'flower') {
            return $this->flower;
        } elseif ($this->item_type === 'complement') {
            return $this->complement;
        }
        return null;
    }

    /**
     * Get the product name for this order item
     */
    public function getProductNameAttribute()
    {
        if ($this->item_type === 'flower') {
            return $this->flower ? $this->flower->name : $this->flower_name;
        } elseif ($this->item_type === 'complement') {
            return $this->complement ? $this->complement->name : 'Complemento';
        }
        return $this->flower_name; // Fallback for backward compatibility
    }
}
