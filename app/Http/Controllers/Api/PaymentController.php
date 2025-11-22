<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Midtrans\Notification;

class PaymentController extends Controller
{
    public function webhook(Request $request)
    {
        $notif = new Notification();
        $transaction = $notif->transaction_status;
        $type = $notif->payment_type;
        $orderId = $notif->order_id;
        $fraud = $notif->fraud_status;

        $order = Order::where('order_code', $orderId)->first();
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Simpan atau Update Payment
        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'payment_type' => $type,
                'gross_amount' => $notif->gross_amount,
                'transaction_status' => $transaction,
                'transaction_time' => $notif->transaction_time,
                'raw_response' => $request->all(),
            ]
        );

        // Update Order Status
        if ($transaction == 'capture') {
            if ($type == 'credit_card') {
                if ($fraud == 'challenge') {
                    $order->update(['payment_status' => 'challenge']);
                } else {
                    $order->update(['payment_status' => 'success']);
                }
            }
        } elseif ($transaction == 'settlement') {
            $order->update(['payment_status' => 'success']);
        } elseif ($transaction == 'pending') {
            $order->update(['payment_status' => 'pending']);
        } elseif ($transaction == 'deny') {
            $order->update(['payment_status' => 'failed']);
            // Kembalikan Stok jika gagal
            foreach ($order->items as $item) {
                Product::increaseStock($item->product_id, $item->qty);
            }
        } elseif ($transaction == 'expire') {
            $order->update(['payment_status' => 'expired']);
            // Kembalikan Stok
            foreach ($order->items as $item) {
                Product::increaseStock($item->product_id, $item->qty);
            }
        } elseif ($transaction == 'cancel') {
            $order->update(['payment_status' => 'cancelled']);
            // Kembalikan Stok
            foreach ($order->items as $item) {
                Product::increaseStock($item->product_id, $item->qty);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}