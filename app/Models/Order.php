<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'total',
        'subtotal',
        'tax',
        'shipping_cost',
        'shipping_type',
        'shipping_address',
        'billing_address',
        'pickup_store',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_info',
        'notes',
        'delivery_date',
        'delivery_time_slot'
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'customer_info' => 'array',
        'pickup_store' => 'array',
        'delivery_date' => 'datetime',
    ];

    // Order status constants
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // Payment status constants
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order items relationship
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Order status history relationship
     */
    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Payments relationship
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Single payment relationship (latest payment)
     */
    public function payment()
    {
        return $this->hasOne(Payment::class)->latest();
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber()
    {
        do {
            $orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled()
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED
        ]);
    }

    /**
     * Check if order is completed
     */
    public function isCompleted()
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Check if order is paid
     */
    public function isPaid()
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }
}
