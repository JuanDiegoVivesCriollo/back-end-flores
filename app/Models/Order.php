<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_customer_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'payment_proof_image',
        'payment_proof_notes',
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
        'delivery_time_slot',
        'recipient_name',
        'recipient_phone',
        'recipient_relationship',
        'special_instructions'
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = 'FDJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            }
        });
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Guest customer relationship
     */
    public function guestCustomer()
    {
        return $this->belongsTo(GuestCustomer::class);
    }

    /**
     * Order items relationship
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Alias for orderItems
     */
    public function items()
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
     * Latest payment relationship
     */
    public function payment()
    {
        return $this->hasOne(Payment::class)->latest();
    }

    /**
     * Check if order is paid
     */
    public function isPaid()
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    /**
     * Check if order can be cancelled
     */
    public function canBeCancelled()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_CONFIRMED => 'Confirmado',
            self::STATUS_PREPARING => 'En Preparación',
            self::STATUS_READY => 'Listo',
            self::STATUS_IN_TRANSIT => 'En Camino',
            self::STATUS_DELIVERED => 'Entregado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Get payment status label
     */
    public function getPaymentStatusLabelAttribute()
    {
        $labels = [
            self::PAYMENT_PENDING => 'Pendiente',
            self::PAYMENT_PAID => 'Pagado',
            self::PAYMENT_FAILED => 'Fallido',
            self::PAYMENT_REFUNDED => 'Reembolsado',
        ];

        return $labels[$this->payment_status] ?? $this->payment_status;
    }

    /**
     * Update order status with history
     */
    public function updateStatus($newStatus, $notes = null)
    {
        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        // Create status history
        $this->statusHistory()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'changed_by' => auth()->id()
        ]);

        return $this;
    }
}
