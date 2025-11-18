<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method',
        'transaction_id',
        'payment_status',
        'amount',
        'currency',
        'payment_details',
        'paid_at',
        'failure_reason',
        'session_token', // Field is now available in database schema
        'status',
        // 'metadata' // Temporarily removed from fillable for testing
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_details' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    // Payment status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIAL_REFUND = 'partial_refund';

    // Payment method constants
    const METHOD_IZIPAY = 'izipay';
    const METHOD_CASH = 'cash_on_delivery';
    const METHOD_TRANSFER = 'bank_transfer';

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->payment_status === self::STATUS_COMPLETED;
    }

    /**
     * Order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Mark payment as completed
     */
    public function markAsCompleted(array $gatewayResponse = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now()
        ]);
    }

    /**
     * Mark payment as failed
     */
    public function markAsFailed(string $reason = '', array $gatewayResponse = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'gateway_response' => $gatewayResponse,
            'failed_at' => now()
        ]);
    }

    /**
     * Scope for completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
