<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutgoingTransaction extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'product_id',
        'qty',
        'price_per_item',
        'total_price',
        'type', // 'online' atau 'offline'
        'order_id',
        'created_by'
    ];

    protected $casts = [
        'price_per_item' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
