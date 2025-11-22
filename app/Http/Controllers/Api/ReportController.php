<?php

namespace App\Http\Controllers\API;

use App\Exports\AllOutgoingTransactionExport;
use App\Exports\OfflineOutgoingTransactionExport;
use App\Exports\OnlineOutgoingTransactionExport;
use App\Exports\OutgoingReportExport;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\IncomingTransaction;
use App\Models\OutgoingTransaction;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StockReportExport;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    // ✅ Get JSON laporan stok
    public function stockReport()
    {
        $data = Product::all()->map(function ($product, $index) {
            $incoming = IncomingTransaction::where('product_id', $product->id)->sum('qty');
            $outgoing = OutgoingTransaction::where('product_id', $product->id)->sum('qty');
            $stok_awal = $product->stock + $outgoing - $incoming;

            return [
                'no' => $index + 1,
                'nama_produk' => $product->name,
                'stok_awal' => $stok_awal,
                'transaksi_masuk' => $incoming,
                'transaksi_keluar' => $outgoing,
                'sisa_stok' => $product->stock,
                'keterangan' => $product->stock <= 5 ? 'Stok Hampir Habis' : 'Tersedia'
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Laporan stok barang berhasil diambil',
            'data' => $data
        ]);
    }

    // ✅ Export ke Excel
    public function exportExcel()
    {
        return Excel::download(new StockReportExport, 'laporan_stok_barang.xlsx');
    }

    // ✅ Export ke PDF
    public function exportPDF()
    {
        $products = Product::all()->map(function ($product, $index) {
            $incoming = IncomingTransaction::where('product_id', $product->id)->sum('qty');
            $outgoing = OutgoingTransaction::where('product_id', $product->id)->sum('qty');
            $stok_awal = $product->stock + $outgoing - $incoming;

            return [
                'no' => $index + 1,
                'nama_produk' => $product->name,
                'stok_awal' => $stok_awal,
                'transaksi_masuk' => $incoming,
                'transaksi_keluar' => $outgoing,
                'sisa_stok' => $product->stock,
                'keterangan' => $product->stock <= 5 ? 'Stok Hampir Habis' : 'Tersedia'
            ];
        });

        $pdf = Pdf::loadView('exports.stock-pdf', ['products' => $products]);
        return $pdf->download('laporan_stok_barang.pdf');
    }
}
