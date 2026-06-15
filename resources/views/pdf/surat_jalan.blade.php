<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Surat Jalan - {{ $distribution->surat_jalan_no }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 5px;
            font-size: 18px;
        }
        .header-info {
            margin-bottom: 20px;
        }
        .header-info table {
            width: 100%;
            border: none;
        }
        .header-info td {
            vertical-align: top;
            padding: 3px 0;
        }
        .header-info .label {
            width: 150px;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center {
            text-align: center !important;
        }
        .text-right {
            text-align: right !important;
        }
        .notes-section {
            margin-bottom: 30px;
        }
        .notes-box {
            border-bottom: 1px dotted #000;
            margin-top: 10px;
            width: 100%;
            height: 20px;
        }
        .signatures {
            width: 100%;
            margin-top: 50px;
        }
        .signatures table {
            width: 100%;
            text-align: center;
            border: none;
        }
        .signatures td {
            width: 50%;
            vertical-align: top;
        }
        .sign-area {
            margin-top: 60px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            display: inline-block;
            width: 200px;
        }
        .status-box {
            margin-top: 30px;
            border: 1px solid #000;
            padding: 10px;
        }
        .status-box p {
            margin: 0 0 5px 0;
        }
        .checkbox {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #000;
            margin-right: 5px;
            vertical-align: middle;
        }
    </style>
</head>
<body>

    <h2>SURAT JALAN</h2>
    
    <div class="header-info">
        <table>
            <tr>
                <td class="label">No. Surat Jalan</td>
                <td>: {{ $distribution->surat_jalan_no }}</td>
            </tr>
            <tr>
                <td class="label">Tanggal</td>
                <td>: {{ $distribution->dispatched_at ? \Carbon\Carbon::parse($distribution->dispatched_at)->translatedFormat('d F Y') : now()->translatedFormat('d F Y') }}</td>
            </tr>
            <tr>
                <td class="label">Gudang Asal</td>
                <td>: {{ $distribution->warehouse->name ?? '-' }} – {{ $distribution->warehouse->address ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Tujuan</td>
                <td>: Toko {{ $distribution->store->name ?? '-' }} – {{ $distribution->store->address ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">No. Referensi Distribusi</td>
                <td>: {{ $distribution->distribution_code }}</td>
            </tr>
        </table>
    </div>

    <h4>Daftar Produk yang Dikirim</h4>
    <table class="items-table">
        <thead>
            <tr>
                <th class="text-center" style="width: 5%;">No</th>
                <th style="width: 15%;">Kode Produk</th>
                <th style="width: 25%;">Nama Produk</th>
                <th style="width: 15%;">Batch/Lot</th>
                <th class="text-center" style="width: 10%;">Qty Diminta</th>
                <th class="text-center" style="width: 10%;">Qty Disetujui</th>
                <th class="text-center" style="width: 10%;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($distribution->items as $index => $item)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $item->batch->product->sku ?? '-' }}</td>
                <td>{{ $item->batch->product->name ?? '-' }}</td>
                <td>{{ $item->batch->batch_code ?? '-' }}</td>
                <td class="text-center">{{ $item->requested_quantity ?? 0 }}</td>
                <td class="text-center">{{ $item->approved_quantity ?? ($item->requested_quantity ?? 0) }}</td>
                <td class="text-center">{{ $item->batch->product->unit->name ?? 'Pcs' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notes-section">
        <strong>Catatan Penyesuaian (jika qty disetujui &ne; qty diminta):</strong>
        <div class="notes-box"></div>
        <div class="notes-box"></div>
    </div>

    <div class="signatures">
        <table>
            <tr>
                <td>
                    <strong>Pihak Pengirim</strong><br>
                    Jabatan: Staff Gudang<br>
                    <div class="sign-area"></div><br>
                    Nama: _______________________<br><br>
                    Tanggal/Jam Kirim: ___________
                </td>
                <td>
                    <strong>Pihak Penerima</strong><br>
                    Jabatan/Toko: ________________<br>
                    <div class="sign-area"></div><br>
                    Nama: _______________________<br><br>
                    Tanggal/Jam Terima: ___________
                </td>
            </tr>
        </table>
    </div>

    <div class="status-box">
        <p>
            <strong>Status:</strong> &nbsp;&nbsp;&nbsp;
            <span class="checkbox"></span> SHIPPED &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="checkbox"></span> DELIVERED &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <span class="checkbox"></span> COMPLETED
        </p>
        <p style="font-size: 11px; font-style: italic; margin-top: 8px;">
            Surat ini menjadi bukti sah bahwa barang telah dikirim dan diterima sesuai jumlah tercantum.
        </p>
    </div>

</body>
</html>
