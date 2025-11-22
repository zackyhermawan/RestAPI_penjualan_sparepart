<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Stok Barang</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #eee; }
        h2 { text-align: center; }
    </style>
</head>
<body>
    <h2>Laporan Stok Barang</h2>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Produk</th>
                <th>Stok Awal</th>
                <th>Transaksi Masuk</th>
                <th>Transaksi Keluar</th>
                <th>Sisa Stok</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($products as $item)
                <tr>
                    <td>{{ $item['no'] }}</td>
                    <td>{{ $item['nama_produk'] }}</td>
                    <td>{{ $item['stok_awal'] }}</td>
                    <td>{{ $item['transaksi_masuk'] }}</td>
                    <td>{{ $item['transaksi_keluar'] }}</td>
                    <td>{{ $item['sisa_stok'] }}</td>
                    <td>{{ $item['keterangan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
