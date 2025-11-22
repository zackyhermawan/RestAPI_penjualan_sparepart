<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomingTransaction;
use App\Models\Order;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    //
    public function indexIncoming(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $productId = $request->input('filter_id');

        $query = IncomingTransaction::with('product');

        // 1. Filter berdasarkan ID Produk
        $query->when($productId, function($q, $productId){
            return $q->where('product_id', $productId);
        });

        // 2. Filter berdasarkan Nama Produk (MENGGUNAKAN whereHas)
        $query->when($search, function($q) use ($search) {
            // Mencari transaksi yang memiliki produk dengan nama yang cocok
            return $q->whereHas('product', function($productQuery) use ($search) {
                $productQuery->where('name', 'like', '%' . $search . '%');
            });
        });

        $transactionIn = $query->latest()->paginate($perPage); // Tambahkan latest() agar urutan data masuk terbaru di atas
        return response()->json($transactionIn);
    }

    public function storeIncoming(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'price_per_item' => 'required|numeric|min:0',
        ]);

        $product = Product::find($request->product_id);
        $totalPrice = $request->qty * $request->price_per_item;

        DB::beginTransaction();
        try{
            IncomingTransaction::create([
                'product_id' => $request->product_id,
                'qty' => $request->qty,
                'price_per_item' => $request->price_per_item,
                'total_price' => $totalPrice,
                'created_by' => Auth::id(),
            ]);

            $product->stock += $request->qty;
            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Transaksi Masuk berhasil dicatat dan stok berhasil ditambah.',
                'product' => $product,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal mencatat Transaksi Masuk: ' . $e->getMessage()], 500);
        }
    }

    public function updateIncomingStock(Request $request, $id)
    {
        // 1. Validasi input
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'price_per_item' => 'required|numeric|min:0',
        ]);

        // 2. Ambil transaksi masuk yang ada
        $incomingTransaction = IncomingTransaction::find($id);

        if (!$incomingTransaction) {
            return response()->json(['error' => 'Transaksi Masuk tidak ditemukan.'], 404);
        }

        // Ambil data lama sebelum update
        $oldProductId = $incomingTransaction->product_id;
        $oldQty = $incomingTransaction->qty;

        // Ambil data baru dari request
        $newProductId = (int) $request->product_id;
        $newQty = (int) $request->qty;
        $newPrice = (float) $request->price_per_item;
        $newTotalPrice = $newQty * $newPrice;

        DB::beginTransaction();
        try {
            // --- LOGIKA PENYESUAIAN STOK ---

            // Kasus 1: Product_id berubah
            if ($oldProductId !== $newProductId) {
                // A. Kurangi stok dari produk lama (seolah-olah transaksi lama dihapus)
                $oldProduct = Product::find($oldProductId);
                if ($oldProduct) {
                    $oldProduct->stock -= $oldQty;
                    // Tambahkan check jika stok lama tidak boleh negatif
                    if ($oldProduct->stock < 0) {
                        // Jika Anda melarang stok negatif
                        DB::rollBack();
                        return response()->json(['error' => 'Gagal update: Stok produk lama tidak mencukupi untuk dikurangi.'], 400);
                    }
                    $oldProduct->save();
                }

                // B. Tambahkan stok ke produk baru
                $newProduct = Product::find($newProductId);
                if ($newProduct) {
                    $newProduct->stock += $newQty;
                    $newProduct->save();
                }

            } else {
                // Kasus 2: Hanya Qty yang berubah (Product_id tetap)
                $stockDifference = $newQty - $oldQty;
                $product = Product::find($oldProductId);

                if ($product) {
                    // Tambahkan check jika pengurangan stok akan menyebabkan negatif
                    if ($product->stock + $stockDifference < 0) {
                        DB::rollBack();
                        return response()->json(['error' => 'Gagal update: Pengurangan kuantitas menyebabkan stok menjadi negatif.'], 400);
                    }
                    $product->stock += $stockDifference;
                    $product->save();
                }
            }

            // --- UPDATE TRANSAKSI ---
            $incomingTransaction->update([
                'product_id' => $newProductId,
                'qty' => $newQty,
                'price_per_item' => $newPrice,
                'total_price' => $newTotalPrice,
                'updated_by' => Auth::id(), // Opsional: catat siapa yang update
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaksi Masuk berhasil diperbarui dan stok disesuaikan.',
                'incoming_transaction' => $incomingTransaction,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal memperbarui Transaksi Masuk: ' . $e->getMessage()], 500);
        }
    }

    public function deleteIncoming($id)
    {
        // 1. Ambil transaksi masuk yang akan dihapus
        $incomingTransaction = IncomingTransaction::find($id);

        // Cek apakah transaksi ditemukan
        if (!$incomingTransaction) {
            return response()->json(['error' => 'Transaksi Masuk tidak ditemukan.'], 404);
        }

        $deletedQty = $incomingTransaction->qty;
        $productId = $incomingTransaction->product_id;

        // 2. Mulai Transaksi Database
        DB::beginTransaction();
        try {
            // A. Ambil produk terkait
            $product = Product::find($productId);

            if (!$product) {
                DB::rollBack();
                return response()->json(['error' => 'Produk terkait tidak ditemukan.'], 404);
            }

            // B. Hapus Transaksi Masuk
            $incomingTransaction->delete();

            // C. Kurangi stok produk
            // Pastikan stok tidak menjadi negatif (jika bisnis logic menghendaki)
            if ($product->stock < $deletedQty) {
                // Ini adalah peringatan/kesalahan jika stok tidak mencukupi untuk dikurangi
                // Anda bisa memilih untuk melempar exception atau membiarkan stok menjadi 0/negatif
                // Tergantung pada aturan bisnis Anda. Saya akan rollback untuk menghindari stok negatif
                DB::rollBack();
                return response()->json(['error' => 'Gagal menghapus: Stok produk (' . $product->stock . ') kurang dari kuantitas yang akan dihapus (' . $deletedQty . ').'], 400);
            }

            $product->stock -= $deletedQty;
            $product->save();

            // D. Commit Transaksi
            DB::commit();

            return response()->json([
                'message' => 'Transaksi Masuk berhasil dihapus dan stok berhasil dikurangi.',
                'product' => $product,
            ], 200);

        } catch (\Exception $e) {
            // Rollback jika ada kegagalan
            DB::rollBack();
            return response()->json(['error' => 'Gagal menghapus Transaksi Masuk: ' . $e->getMessage()], 500);
        }
    }

    // Controller transaksi masuk->offline
    public function indexOutgoingOffline(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $productId = $request->input('filter_id');

        $query = OutgoingTransaction::with('product')->where('type', 'offline');

        // 1. Filter berdasarkan ID Produk
        $query->when($productId, function($q, $productId){
            return $q->where('product_id', $productId);
        });

        // 2. Filter berdasarkan Nama Produk (MENGGUNAKAN whereHas)
        $query->when($search, function($q) use ($search) {
            // Mencari transaksi yang memiliki produk dengan nama yang cocok
            return $q->whereHas('product', function($productQuery) use ($search) {
                $productQuery->where('name', 'like', '%' . $search . '%');
            });
        });

        $transactionOut = $query->latest()->paginate($perPage); // Tambahkan latest() agar urutan data masuk terbaru di atas
        return response()->json($transactionOut);
    }

    public function storeOutgoingOffline(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            // Gunakan harga jual produk, atau bisa diinput manual oleh admin
            'price_per_item' => 'required|numeric|min:0', 
        ]);

        $product = Product::find($request->product_id);

        if ($product->stock < $request->qty) {
            return response()->json(['error' => 'Stok tidak cukup untuk produk: ' . $product->name], 400);
        }

        $totalPrice = $request->qty * $request->price_per_item;

        DB::beginTransaction();
        try {
            OutgoingTransaction::create([
                'product_id' => $request->product_id,
                'qty' => $request->qty,
                'price_per_item' => $request->price_per_item,
                'total_price' => $totalPrice,
                'type' => 'offline', 
                'order_id' => null, 
                'created_by' => Auth::id(), 
            ]);

            $product->stock -= $request->qty;
            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Transaksi Keluar (Offline) berhasil dicatat dan stok berhasil dikurangi.',
                'product' => $product,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal mencatat Transaksi Keluar: ' . $e->getMessage()], 500);
        }
    }

    public function updateOutgoingOffline(Request $request, $id)
    {
        // 1. Validasi Input
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
            'price_per_item' => 'required|numeric|min:0',
        ]);

        // 2. Ambil Transaksi Keluar yang Ada
        $outgoingTransaction = OutgoingTransaction::find($id);

        if (!$outgoingTransaction) {
            return response()->json(['error' => 'Transaksi Keluar tidak ditemukan.'], 404);
        }

        // 3. Verifikasi Type Transaksi
        if ($outgoingTransaction->type !== 'offline') {
            return response()->json(['error' => 'Transaksi tidak dapat diupdate. Hanya transaksi bertipe "offline" yang diizinkan.'], 403);
        }

        // Ambil data lama dan baru
        $oldProductId = $outgoingTransaction->product_id;
        $oldQty = $outgoingTransaction->qty;
        
        $newProductId = (int) $request->product_id;
        $newQty = (int) $request->qty;
        $newPrice = (float) $request->price_per_item;
        $newTotalPrice = $newQty * $newPrice;

        DB::beginTransaction();
        try {
            // --- LOGIKA PENYESUAIAN STOK ---

            // Kasus 1: Product_id berubah
            if ($oldProductId !== $newProductId) {
                
                // A. Kembalikan stok ke produk lama (seolah-olah transaksi lama dibatalkan)
                $oldProduct = Product::find($oldProductId);
                if ($oldProduct) {
                    $oldProduct->stock += $oldQty;
                    $oldProduct->save();
                }

                // B. Kurangi stok dari produk baru (berdasarkan Qty baru)
                $newProduct = Product::find($newProductId);
                if (!$newProduct) {
                    DB::rollBack();
                    return response()->json(['error' => 'Produk baru tidak ditemukan.'], 404);
                }
                // Cek apakah stok baru mencukupi
                if ($newProduct->stock < $newQty) {
                    DB::rollBack();
                    return response()->json(['error' => 'Stok produk baru (' . $newProduct->name . ') tidak cukup untuk kuantitas ' . $newQty], 400);
                }
                $newProduct->stock -= $newQty;
                $newProduct->save();

            } else { 
                // Kasus 2: Product_id tetap, hanya Qty yang berubah
                $stockDifference = $oldQty - $newQty; // Positif = kembalikan stok, Negatif = kurangi stok lebih banyak
                $product = Product::find($oldProductId);

                if (!$product) {
                    DB::rollBack();
                    return response()->json(['error' => 'Produk terkait tidak ditemukan.'], 404);
                }

                // Cek apakah pengurangan stok tambahan akan menyebabkan negatif
                if ($product->stock + $stockDifference < 0) {
                    DB::rollBack();
                    return response()->json(['error' => 'Gagal update: Pengurangan kuantitas menyebabkan stok menjadi negatif.'], 400);
                }
                
                // Lakukan penyesuaian stok
                $product->stock += $stockDifference; // Jika Qty berkurang, $stockDifference positif, stok bertambah (dikembalikan)
                                                    // Jika Qty bertambah, $stockDifference negatif, stok berkurang (ditarik lebih banyak)
                $product->save();
            }

            // --- UPDATE TRANSAKSI ---
            $outgoingTransaction->update([
                'product_id' => $newProductId,
                'qty' => $newQty,
                'price_per_item' => $newPrice,
                'total_price' => $newTotalPrice,
                'updated_by' => Auth::id(), 
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaksi Keluar Offline berhasil diperbarui dan stok disesuaikan.',
                'outgoing_transaction' => $outgoingTransaction,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Gagal memperbarui Transaksi Keluar Offline: ' . $e->getMessage()], 500);
        }
    }

    public function deleteOutgoingOffline($id)
    {
        // 1. Ambil transaksi keluar yang akan dihapus
        $outgoingTransaction = OutgoingTransaction::find($id);

        if (!$outgoingTransaction) {
            return response()->json(['error' => 'Transaksi Keluar tidak ditemukan.'], 404);
        }

        // 2. Verifikasi Type Transaksi
        if ($outgoingTransaction->type !== 'offline') {
            return response()->json(['error' => 'Transaksi tidak dapat dihapus. Hanya transaksi bertipe "offline" yang diizinkan.'], 403);
        }

        $deletedQty = $outgoingTransaction->qty;
        $productId = $outgoingTransaction->product_id;

        // 3. Mulai Transaksi Database
        DB::beginTransaction();
        try {
            // A. Ambil produk terkait
            $product = Product::find($productId);

            if (!$product) {
                DB::rollBack();
                return response()->json(['error' => 'Produk terkait tidak ditemukan.'], 404);
            }

            // B. Hapus Transaksi Keluar
            $outgoingTransaction->delete();

            // C. Kembalikan stok produk
            // Karena ini adalah pembatalan penjualan, stok harus ditambah kembali.
            $product->stock += $deletedQty;
            $product->save();

            // D. Commit Transaksi
            DB::commit();

            return response()->json([
                'message' => 'Transaksi Keluar Offline berhasil dihapus dan stok berhasil dikembalikan.',
                'product' => $product,
            ], 200);

        } catch (\Exception $e) {
            // Rollback jika ada kegagalan
            DB::rollBack();
            return response()->json(['error' => 'Gagal menghapus Transaksi Keluar Offline: ' . $e->getMessage()], 500);
        }
    }

    // Controller transaksi keluar->online
    public function indexOutgoing(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $productId = $request->input('filter_id');

        $query = OutgoingTransaction::with(['product', 'order'])->where('type', 'online');

        // 1. Filter berdasarkan ID Produk
        $query->when($productId, function($q, $productId){
            return $q->where('product_id', $productId);
        });

        // 2. Filter berdasarkan Nama Produk (MENGGUNAKAN whereHas)
        $query->when($search, function($q) use ($search) {
            // Mencari transaksi yang memiliki produk dengan nama yang cocok
            return $q->whereHas('product', function($productQuery) use ($search) {
                $productQuery->where('name', 'like', '%' . $search . '%');
            });
        });

        $transactionOutOnline = $query->latest()->paginate($perPage); // Tambahkan latest() agar urutan data masuk terbaru di atas
        return response()->json($transactionOutOnline);
    }

    public function updateShippingStatus(Request $request, $id)
    {
        $request->validate([
            'shipping_status'=>'required|string',
        ]);

        $stockOut = OutgoingTransaction::with('order')->find($id);

        if(!$stockOut) {
            return response()->json(['message'=>'Transaksi keluar tidak ditemukan'], 404);
        }

        if($stockOut->type !== 'online'){
            return response()->json(['message'=>'Hanya transaksi online yang dapat ubah shipping status'], 403);
        }

        if(!$stockOut->order){
            return response()->json(['message'=>'Relasi Order tidak ditemukan untuk transaksi ini.'], 404);
        }

        $stockOut->order->shipping_status = $request->shipping_status;
        $stockOut->order->save();

        return response()->json([
            'message'=>'Shipping status berhasil di perbarui',
            'outgoing_transaction'=>$stockOut->load('order')
        ]);
    }

    public function confirmDelivery($id)
    {
        $stockOut = OutgoingTransaction::where('order_id', $id)->first();

        if(!$stockOut || !$stockOut->order){
            return response()->json(['message'=>'Transaksi atau order tidak ditemukan'], 404);
        }
        
        $order = $stockOut->order;

        if($order->shipping_status !== 'Dikirim'){
            return response()->json([
                'message' => 'Pesanan hanya bisa dikonfirmasi diterima jika statusnya "Dikirim". Status saat ini: ' . $order->shipping_status
            ], 403);
        }

        $order->shipping_status = 'Diterima';
        $order->save();

        return response()->json([
            'message' => 'Pesanan berhasil dikonfirmasi Diterima.',
            'new_status' => $order->shipping_status,
            'outgoing_transaction' => $stockOut->load('order')
        ]);
    }
}
