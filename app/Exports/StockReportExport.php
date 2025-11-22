<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\IncomingTransaction;
use App\Models\OutgoingTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockReportExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $data = Product::all()->map(function ($product, $index) {
            $incoming = IncomingTransaction::where('product_id', $product->id)->sum('qty');
            $outgoing = OutgoingTransaction::where('product_id', $product->id)->sum('qty');
            $stok_awal = $product->stock + $outgoing - $incoming;

            return [
                'No' => $index + 1,
                'Nama Produk' => $product->name,
                'Stok Awal' => $stok_awal,
                'Transaksi Masuk' => $incoming,
                'Transaksi Keluar' => $outgoing,
                'Sisa Stok' => $product->stock,
                'Keterangan' => $product->stock <= 5 ? 'Stok Hampir Habis' : 'Tersedia'
            ];
        });

        return $data;
    }

    public function headings(): array
    {
        return ['No', 'Nama Produk', 'Stok Awal', 'Transaksi Masuk', 'Transaksi Keluar', 'Sisa Stok', 'Keterangan'];
    }
}
