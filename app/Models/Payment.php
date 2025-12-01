<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'direct_payment_order_id',
        'transaction_id',
        'session_token',
        'payment_method',
        'payment_gateway',
        'amount',
        'currency',
        'status',
        'gateway_response',
        'payment_details',
        'metadata',
        'paid_at',
        'expires_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'payment_details' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Payment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    // Payment methods
    const METHOD_CARD = 'card';
    const METHOD_TRANSFER = 'transfer';
    const METHOD_CASH = 'cash';
    const METHOD_YAPE = 'yape';
    const METHOD_PLIN = 'plin';

    // Payment gateways
    const GATEWAY_IZIPAY = 'izipay';
    const GATEWAY_MANUAL = 'manual';

    /**
     * Order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Direct payment order relationship
     */
    public function directPaymentOrder()
    {
        return $this->belongsTo(DirectPaymentOrder::class);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment has expired
     */
    public function hasExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted($transactionId = null, $gatewayResponse = null)
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'gateway_response' => $gatewayResponse ?? $this->gateway_response,
            'paid_at' => now(),
        ]);

        // Update order payment status
        if ($this->order) {
            $this->order->update(['payment_status' => Order::PAYMENT_PAID]);
        }

        return $this;
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed($gatewayResponse = null)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'gateway_response' => $gatewayResponse ?? $this->gateway_response,
        ]);

        // Update order payment status
        if ($this->order) {
            $this->order->update(['payment_status' => Order::PAYMENT_FAILED]);
        }

        return $this;
    }
}
