<?php

namespace App\Http\Controllers\Api;

use App\Helpers\OrderHelper;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;
use phpDocumentor\Reflection\Types\Object_;

class OrderController extends Controller
{
    public function __construct()
    {
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function checkout(Request $request)
    {
        \Log::info('Payload Checkout dari Vue:', $request->all());
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string',
            'province' => 'required|string',
            'city' => 'required|string',
            'district' => 'required|string',
            'postal_code' => 'required|string',
            'address_detail' => 'required|string'
        ]);

        if (!Auth::check()) {
            // Jika pengguna tidak login, hentikan dan kembalikan 401 Unauthorized
            return response()->json(['error' => 'Unauthorized. Harap login kembali.'], 401);
        }
        
        $user = Auth::user();
        $items = $request->items;
        $totalAmount = 0;
        $cart = []; 

        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            if ($product->stock < $item['qty']) {
                return response()->json(['error' => 'Stok tidak cukup untuk produk: ' . $product->name], 400);
            }

            $subTotal = $product->price * $item['qty'];
            $totalAmount += $subTotal;

            $cart[] = [
                'product_id' => $item['product_id'],
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'variant' => $product->variant,
                'price' => $product->price,
                'qty' => $item['qty'],
                'subtotal' => $subTotal,
            ];
        }

        $shipping_cost = 0;
        $discountAmount = 0;
        $grandTotal = ($totalAmount + $shipping_cost) - $discountAmount;

        DB::beginTransaction();
        try {
        $order = Order::create([
            'order_code' => OrderHelper::generateOrderCode(),
            'user_id' => $user->id ?? null,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'shipping_cost' => $shipping_cost,
            'grand_total' => $grandTotal,

            'order_status' => 'pending',
            'payment_status' => 'pending',
            'shipping_status' => 'menunggu konfirmasi',

            'payment_method' => 'midtrans',
            'shipping_method' => null,

            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'customer_email' => $user->email ?? null,
            'province' => $request->province,
            'city' => $request->city,
            'district' => $request->district,
            'postal_code' => $request->postal_code,
            'address_detail' => $request->address_detail,
        ]);

        // Buat Order Items dan Kurangi Stok
        foreach ($cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'product_sku' => $item['product_sku'],
                'variant' => $item['variant'],
                'qty' => $item['qty'],
                'price' => $item['price'],
                'subtotal' => $item['subtotal'],
            ]);
        }
        DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Gagal membuat order Checkout:', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Gagal membuat order: Periksa log server untuk detail.'], 500);
        }

        // Integrasi Midtrans
        $params = [
            'transaction_details' => [
                'order_id' => $order->order_code,
                'gross_amount' => $grandTotal,
            ],
            'customer_details' => [
                'first_name' => $request->customer_name,
                'email' => $order->customer_email,
                'phone' => $request->customer_phone
            ],
            'item_details' => array_map(function ($item) {
                return [
                    'id' => $item['product_id'],
                    'price' => $item['price'],
                    'quantity' => $item['qty'],
                    'name' => $item['product_name'],
                ];
            }, $cart),
            'callbacks' => [
                'finish' => 'http://localhost:5173/sukses'
            ],
        ];

        $midtransTransactionId = $order->order_code;

        $order->midtrans_order_id = $midtransTransactionId; // Tetapkan nilai
        $order->save();

        $order->payment()->create([
            'provider' => 'midtrans',
            'payment_type' => $order->payment_type, 
            'gross_amount' => $order->grand_total,
            'transaction_status' => 'pending', 
            'fraud_status' => 'accept', 
            'transaction_id' => $midtransTransactionId, 
            'va_number' => null, 
            'transaction_time' => now(), 
            
        ]);

        $snapToken = Snap::getSnapToken($params);

        return response()->json([
            'message' => 'Order berhasil dibuat',
            'order' => $order,
            'snap_token' => $snapToken,
            // Expose client_key so the frontend can load snap.js and call snap.pay
            'midtrans_client_key' => config('services.midtrans.client_key'),
        ]);
    }

    public function midtransCallback(Request $request)
    {
        // 1. Set konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');

        // 2. Ambil notifikasi
        try {
            if (app()->environment('local')) {
                $notif = (Object) json_decode($request->getContent());
            } else {
                $notif = new Notification();
            }
        } catch (\Exception $e) {
            return response('Verifikasi Gagal: ' . $e->getMessage(), 401);
        }

        // 3. Ambil data penting
        $transactionStatus = $notif->transaction_status ?? null;
        $orderCode = $notif->order_id ?? null;
        $fraudStatus = $notif->fraud_status ?? null;

        if (!$orderCode) {
            return response('Order ID tidak ditemukan', 400);
        }

        // 4. Cari order di database
        $order = Order::where('order_code', $orderCode)->with('items.product')->first();
        if (!$order) {
            return response('Order tidak ditemukan', 404);
        }

        if($order->payment_status == 'settlement') {
            return response('Ok (already proccessed)', 200);
        }

        $newPaymentStatus = $order->payment_status;
        $newOrderStatus = $order->order_status;
        $newShippingStatus = $order->shipping_status;
        $shouldUpdateStock = false;

        if($transactionStatus == 'capture' && $fraudStatus == 'accept') {
            $newPaymentStatus = 'settlement';
            $newOrderStatus = 'paid';
            $newShippingStatus = 'dikemas';
            $shouldUpdateStock = true;

        } elseif($transactionStatus == 'settlement') {
            $newPaymentStatus = 'settlement';
            $newOrderStatus = 'paid';
            $newShippingStatus = 'dikemas';
            $shouldUpdateStock = true;

        } elseif(in_array($transactionStatus, ['cancel', 'deny', 'expired'])) {
            $newPaymentStatus = 'failed';
            $newOrderStatus = 'canceled';
            $newShippingStatus = 'canceled';
        }

        DB::beginTransaction();
        try {
            $order->update([
                'payment_status' => $newPaymentStatus,
                'order_status' => $newOrderStatus,
                'shipping_status' => $newShippingStatus,
                'payment_method' => $notif->payment_type ?? $order->payment_method
            ]);

            if($shouldUpdateStock) {
                foreach ($order->items as $item) {
                    $product = $item->product;

                    if(!$product) continue;

                    $product->stock -= $item->qty;
                    $product->save();

                    OutgoingTransaction::create([
                        'product_id'=>$product->id,
                        'qty' => $item->qty,
                        'price_per_item' => $item->price,
                        'total_price' => $item->subtotal,
                        'type' => 'online',
                        'order_id' => $order->id,
                    ]); 
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response('Gagal update database: ' . $e->getMessage(), 500);
        }

        // 6. Response ke Midtrans
        return response('OK', 200);
    }

    public function myOrders()
    {
        $orders = Auth::user()->transactions()->with('items.product.category', 'payment')->get();  // Ganti transactions() ke orders()
        return response()->json($orders);
    }
     

    public function showOrder($id)
    {
        // $order = Order::where('id', $id)->where('user_id', Auth::id())->with('items.product', 'payment')->firstOrFail();
        $orders = Order::with(['outgoing_transactions', 'items.product'])
        ->where('user_id', Auth::id())
        ->get();
        return response()->json($orders);

    }

    // Untuk Admin
    public function allOrders()
    {
        $orders = Order::with(['user', 'outgoing_transactions', 'items.product', 'payment'])->where('payment_status', 'pending')->get();
        return response()->json($orders);
    }

    public function updateShippingStatus(Request $request, $id)
    {
        $request->validate(['shipping_status' => 'required|string']);
        $order = Order::findOrFail($id);
        $order->update(['shipping_status' => $request->shipping_status]);
        return response()->json(['message' => 'Status pengiriman diperbarui']);
    }
}