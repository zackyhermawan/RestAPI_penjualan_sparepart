<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_code',
        'user_id',
        'total_amount',
        'discount_amount',
        'shipping_cost',
        'grand_total',
        'order_status',
        'payment_status',
        'shipping_status',
        'payment_method',
        'shipping_method',
        'midtrans_order_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'province',
        'city',
        'district',
        'postal_code',
        'address_detail',
    ];

    protected $casts = [
        'shipping_address' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function outgoing_transactions()
    {
        return $this->hasMany(OutgoingTransaction::class, 'order_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
