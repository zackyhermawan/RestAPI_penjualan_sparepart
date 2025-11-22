<?php

namespace App\Helpers;

use App\Models\Order;  // Tambahkan ini untuk menggunakan model Order

class OrderHelper
{
    public static function generateOrderCode()
    {
        return 'INV-' . date('YmdHis') . '-' . rand(1000, 9999);
    }

}