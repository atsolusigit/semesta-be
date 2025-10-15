<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lost Event Report</title>
    <style>
        @page {
            margin: 15mm 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
        }

        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            font-weight: bold;
        }

        .header p {
            margin: 3px 0;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
            text-align: center;
            font-size: 7px;
        }

        td {
            font-size: 7px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .wrap-text {
            word-wrap: break-word;
            white-space: normal;
        }

        .no-wrap {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN LOST EVENT</h2>
        {{-- <p>{{ str_replace('_', ' ', $departmentName) }}</p> --}}
        {{-- <p>Tahun: {{ $year }}</p> --}}
        <p>Tanggal Cetak: {{ date('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 2%;">No</th>
                <th style="width: 3%;">Tahun</th>
                <th style="width: 8%;">Risk Owner</th>
                <th style="width: 6%;">Jenis Risiko</th>
                <th style="width: 8%;">Nama Kejadian</th>
                <th style="width: 10%;">Identifikasi Kejadian</th>
                <th style="width: 6%;">Kategori Kejadian</th>
                <th style="width: 8%;">Sumber Penyebab</th>
                <th style="width: 8%;">Penyebab</th>
                <th style="width: 8%;">Penanganan</th>
                <th style="width: 10%;">Deskripsi</th>
                <th style="width: 6%;">Pihak Terkait</th>
                <th style="width: 5%;">Status Asuransi</th>
                <th style="width: 6%;">Penjelasan Kerugian</th>
                <th style="width: 6%;">Nilai Kerugian</th>

                <!-- Kolom tambahan (ditambahkan tanpa mengubah tampilan utama) -->
                <th style="width: 6%;">Kejadian Berulang</th>
                <th style="width: 6%;">Frekuensi Kejadian</th>
                <th style="width: 10%;">Mitigasi Yang Direncanakan</th>
                <th style="width: 8%;">Realisasi Mitigasi</th>
                <th style="width: 8%;">Perbaikan Mendatang</th>
                <th style="width: 6%;">Nilai Premi</th>
                <th style="width: 6%;">Nilai Klaim</th>
                <th style="width: 5%;">Realisasi (%)</th>
                {{-- <th style="width: 5%;">Tipe Risiko</th> --}}
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td class="text-center">{{ $item['no'] }}</td>
                <td class="text-center">{{ $item['tahun'] }}</td>
                <td class="wrap-text">{{ $item['risk_owner_department'] }}</td>
                <td class="wrap-text">{{ $item['jenis_risiko'] }}</td>
                <td class="wrap-text">{{ $item['nama_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['identifikasi_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['kategori_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['sumber_penyebab_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['penyebab_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['penanganan_saat_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['deskripsi_kejadian'] }}</td>
                <td class="wrap-text">{{ $item['pihak_terkait'] }}</td>
                <td class="text-center">{{ $item['status_asuransi'] }}</td>
                <td class="wrap-text">{{ $item['penjelasan_kerugian'] }}</td>
                <td class="text-right">{{ $item['nilai_kerugian_formatted'] }}</td>

                <!-- Data kolom tambahan -->
                <td class="text-center">{{ $item['kejadian_berulang'] ?? '' }}</td>
                <td class="text-center">{{ $item['frekuensi_kejadian'] ?? '' }}</td>
                <td class="wrap-text">{{ $item['mitigasi_yang_direncanakan'] ?? '' }}</td>
                <td class="wrap-text">{{ $item['realisasi_mitigasi'] ?? '' }}</td>
                <td class="wrap-text">{{ $item['perbaikan_mendatang'] ?? '' }}</td>
                <td class="text-right">{{ $item['nilai_premi_formatted'] ?? '' }}</td>
                <td class="text-right">{{ $item['nilai_klaim_formatted'] ?? '' }}</td>
                <td class="text-center">{{ $item['realization_percentage'] ?? '' }}</td>
                {{-- <td class="text-center">{{ strtoupper($item['type'] ?? '') }}</td> --}}
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
