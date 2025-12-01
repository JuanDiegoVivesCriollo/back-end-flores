<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DirectPaymentOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'status',
        'payment_status',
        'total',
        'subtotal',
        'shipping_cost',
        'shipping_type',
        'shipping_address',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_document_type',
        'customer_document_number',
        'recipient_name',
        'recipient_phone',
        'delivery_date',
        'delivery_time_slot',
        'notes',
        'items',
        'payment_code',
        'google_maps_link',
        'session_token'
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_address' => 'array',
        'items' => 'array',
        'delivery_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'FDJD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            }
        });
    }

    /**
     * Payments relationship
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Latest payment
     */
    public function payment()
    {
        return $this->hasOne(Payment::class)->latest();
    }
}
