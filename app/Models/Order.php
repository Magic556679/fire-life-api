<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_no',
        'payment_trade_no',
        'ecpay_trade_no',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'items_snapshot',
        'total_amount',
        'delivery_type',
        'store_info',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'items_snapshot' => 'array',
        'store_info'     => 'array',
        'paid_at'        => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
