<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'payment_type',
        'gross_amount',
        'transaction_status',
        'fraud_status',
        'transaction_id',
        'va_number',
        'transaction_time',
        'settlement_time',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'transaction_time' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
