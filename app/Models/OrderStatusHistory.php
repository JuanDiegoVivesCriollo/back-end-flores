<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'notes'
    ];

    /**
     * Order relationship
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * User who changed the status
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
