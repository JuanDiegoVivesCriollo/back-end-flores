<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DraftOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'draft_number',
        'cart_data',
        'customer_info',
        'shipping_address',
        'billing_address',
        'shipping_type',
        'delivery_date',
        'delivery_time_slot',
        'customer_notes',
        'subtotal',
        'tax',
        'shipping_cost',
        'total',
        'expires_at',
        'converted_to_order_id'
    ];

    protected $casts = [
        'cart_data' => 'array',
        'customer_info' => 'array',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'delivery_date' => 'datetime',
        'expires_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Generate unique draft number
     */
    public static function generateDraftNumber()
    {
        do {
            $draftNumber = 'DRAFT-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('draft_number', $draftNumber)->exists());

        return $draftNumber;
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Converted order relationship
     */
    public function convertedOrder()
    {
        return $this->belongsTo(Order::class, 'converted_to_order_id');
    }

    /**
     * Check if draft has expired
     */
    public function hasExpired()
    {
        return $this->expires_at && now()->isAfter($this->expires_at);
    }

    /**
     * Convert draft to real order (when payment is successful)
     */
    public function convertToOrder()
    {
        if ($this->converted_to_order_id) {
            return Order::find($this->converted_to_order_id);
        }

        \DB::beginTransaction();
        try {
            // Create the real order
            $orderData = [
                'user_id' => $this->user_id,
                'order_number' => Order::generateOrderNumber(),
                'status' => Order::STATUS_CONFIRMED, // Directly to confirmed since payment was successful
                'total' => $this->total,
                'subtotal' => $this->subtotal,
                'tax' => $this->tax,
                'shipping_cost' => $this->shipping_cost,
                'shipping_type' => $this->shipping_type,
                'shipping_address' => $this->shipping_address,
                'billing_address' => $this->billing_address,
                'customer_name' => $this->shipping_address['name'] ?? null,
                'customer_email' => $this->user ? $this->user->email : ($this->customer_info['email'] ?? null),
                'customer_phone' => $this->shipping_address['phone'] ?? null,
                'customer_info' => $this->customer_info ? json_encode($this->customer_info) : null,
                'notes' => $this->customer_notes,
                'delivery_date' => $this->delivery_date,
                'delivery_time_slot' => $this->delivery_time_slot
            ];

            $order = Order::create($orderData);

            // Create order items and reduce stock
            foreach ($this->cart_data as $item) {
                $flower = Flower::find($item['flower_id']);

                if (!$flower || $flower->stock < $item['quantity']) {
                    $flowerName = $flower ? $flower->name : 'Unknown';
                    throw new \Exception("Insufficient stock for flower: {$flowerName}");
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'flower_id' => $item['flower_id'],
                    'flower_name' => $flower->name,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['total']
                ]);

                // NOW reduce stock (only when payment is confirmed)
                $flower->decrement('stock', $item['quantity']);
            }

            // Create status history
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'confirmed',
                'notes' => 'Order confirmed after successful payment',
                'changed_by' => 'system'
            ]);

            // Mark this draft as converted
            $this->update(['converted_to_order_id' => $order->id]);

            \DB::commit();

            return $order;

        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}
