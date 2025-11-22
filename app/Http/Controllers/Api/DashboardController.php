<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutgoingTransaction;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Mengambil dan mengagregasi data untuk 4 kartu metrik utama.
     * Metrik dihitung untuk bulan berjalan (Current Month)
     * dan dibandingkan dengan bulan sebelumnya (Previous Month).
     */
    public function getSalesMetrics()
    {
        // 1. Definisikan Periode Waktu
        $currentMonthStart = Carbon::now()->startOfMonth();
        $previousMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $previousMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // 2. Query Utama: Penjualan (Hanya order yang sudah Selesai/Settlement)
        $baseQuery = Order::where('payment_status', 'settlement');

        // Total Pendapatan Bulan Ini (Current Month Revenue - CMR)
        $cmr = $baseQuery->clone()
            ->where('created_at', '>=', $currentMonthStart)
            ->sum('total_amount');

        // Total Pendapatan Bulan Lalu (Previous Month Revenue - PMR)
        $pmr = $baseQuery->clone()
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->sum('total_amount');

        // Total Transaksi Bulan Ini (Current Month Transactions - CMT)
        $cmt = $baseQuery->clone()
            ->where('created_at', '>=', $currentMonthStart)
            ->count();
        
        // Total Transaksi Bulan Lalu (Previous Month Transactions - PMT)
        $pmt = $baseQuery->clone()
            ->whereBetween('created_at', [$previousMonthStart, $previousMonthEnd])
            ->count();

        // 3. Menghitung Metrik Turunan dan Perubahan Persentase
        
        // Perubahan Pendapatan
        $revenueChange = $pmr > 0 ? (($cmr - $pmr) / $pmr) * 100 : ($cmr > 0 ? 100 : 0);

        // Rata-rata Nilai Transaksi (Average Order Value - AOV)
        $aov = $cmt > 0 ? $cmr / $cmt : 0;
        
        // Rata-rata Nilai Transaksi Bulan Lalu (untuk perbandingan)
        $aov_pm = $pmt > 0 ? $pmr / $pmt : 0;
        
        // Perubahan AOV
        $aovChange = $aov_pm > 0 ? (($aov - $aov_pm) / $aov_pm) * 100 : ($aov > 0 ? 100 : 0);

        // 4. Stok Kritis (Asumsi stok kritis < 20)
        $lowStockCount = Product::where('stock', '<', 20)->count();

        return response()->json([
            'total_revenue' => number_format($cmr, 2, '.', ''), // Pendapatan saat ini
            'revenue_change_percent' => round($revenueChange, 2), // Perubahan vs Bulan Lalu
            
            'total_transactions' => $cmt, // Total Penjualan (Kuantitas Transaksi)
            'transaction_change_percent' => round($pmt > 0 ? (($cmt - $pmt) / $pmt) * 100 : ($cmt > 0 ? 100 : 0), 2),
            
            'average_order_value' => number_format($aov, 2, '.', ''), // Rata-rata Transaksi
            'aov_change_percent' => round($aovChange, 2),
            
            'low_stock_count' => $lowStockCount, // Stok Kritis
        ]);
    }

    /**
     * Mengambil daftar 5 produk dari transaksi terbaru yang sudah settlement.
     */
    public function getLatestSales()
    {
        // 🔹 Ambil transaksi online yang sukses
        $onlineSales = Order::where('payment_status', 'settlement')
            ->with(['items.product'])
            ->get()
            ->map(function ($order) {
                $item = $order->items->first();
                if (!$item) return null;

                return [
                    'date' => Carbon::parse($order->created_at)->format('Y-m-d H:i'),
                    'order_code' => $order->order_code,
                    'product_name' => $item->product->name ?? 'Produk Tidak Ditemukan',
                    'qty' => $item->qty,
                    'amount' => (float) $item->subtotal,
                    'source' => 'Online',
                    'created_at' => $order->created_at,
                ];
            })
            ->filter(); 

        // 🔹 Ambil transaksi offline dari tabel outgoing_transactions
        $offlineSales = OutgoingTransaction::where('type', 'offline')
            ->with('product')
            ->get()
            ->map(function ($trx) {
                return [
                    'date' => Carbon::parse($trx->created_at)->format('Y-m-d H:i'),
                    'order_code' => 'OFF-' . str_pad($trx->id, 5, '0', STR_PAD_LEFT),
                    'product_name' => $trx->product->name ?? 'Produk Tidak Ditemukan',
                    'qty' => $trx->qty,
                    'amount' => (float) $trx->total_price,
                    'source' => 'Offline',
                    'created_at' => $trx->created_at,
                ];
            });

        // 🔹 Gabungkan, urutkan berdasarkan waktu terbaru, ambil 5 data saja
        $latestSales = $onlineSales
            ->merge($offlineSales)
            ->sortByDesc('created_at')
            ->take(5)
            ->values();

        return response()->json($latestSales);
    }

    public function getRevenueTrend()
{
    $months = collect();
    $now = now();

    for ($i = 5; $i >= 0; $i--) {
        $month = $now->copy()->subMonths($i);
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $total = Order::where('payment_status', 'settlement')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_amount');

        $months->push([
            'month' => $month->format('M Y'),
            'total' => (float) $total,
        ]);
    }

    return response()->json($months);
}

}