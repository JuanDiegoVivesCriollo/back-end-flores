<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'dni',
        'session_token',
        'is_registered',
        'last_order_at',
        'total_orders',
        'total_spent'
    ];

    protected $casts = [
        'is_registered' => 'boolean',
        'last_order_at' => 'datetime',
        'total_orders' => 'integer',
        'total_spent' => 'decimal:2',
    ];

    /**
     * Orders relationship
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'guest_customer_id');
    }

    /**
     * Get or create a guest customer by email or phone
     */
    public static function findOrCreateByContact(array $data)
    {
        // Try to find existing customer by email or phone
        $customer = self::where('email', $data['email'])
            ->orWhere('phone', $data['phone'])
            ->first();

        if ($customer) {
            // Update data if found
            $customer->update([
                'full_name' => $data['full_name'],
                'dni' => $data['dni'] ?? $customer->dni,
            ]);
            return $customer;
        }

        // Create new guest customer
        return self::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'dni' => $data['dni'] ?? null,
            'session_token' => bin2hex(random_bytes(32)),
            'is_registered' => false,
            'total_orders' => 0,
            'total_spent' => 0,
        ]);
    }

    /**
     * Increment order stats
     */
    public function incrementOrderStats($orderTotal)
    {
        $this->increment('total_orders');
        $this->increment('total_spent', $orderTotal);
        $this->update(['last_order_at' => now()]);
    }
}
