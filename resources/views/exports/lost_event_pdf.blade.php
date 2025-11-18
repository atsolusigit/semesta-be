<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lost Event Report</title>
    <style>
        @page {
            margin: 15mm 10mm;
            size: A4 landscape;
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
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
            overflow: hidden;
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
            word-break: break-word;
        }

        .no-wrap {
            white-space: nowrap;
        }

        /* Khusus untuk kolom nilai agar turun ke bawah */
        .nilai-column {
            max-width: 80px;
            word-wrap: break-word;
            word-break: break-all;
            white-space: normal;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN LOST EVENT</h2>
        <p>{{ str_replace('_', ' ', $departmentName) }}</p>
        <p>Tanggal Cetak: {{ date('d/m/Y') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 2%;">No</th>
                <th style="width: 3%;">Tahun</th>
                <th style="width: 6%;">Risk Owner</th>
                <th style="width: 5%;">Jenis Risiko</th>
                <th style="width: 6%;">Nama Kejadian</th>
                <th style="width: 7%;">Identifikasi Kejadian</th>
                <th style="width: 5%;">Kategori Kejadian</th>
                <th style="width: 6%;">Sumber Penyebab</th>
                <th style="width: 6%;">Penyebab</th>
                <th style="width: 6%;">Penanganan</th>
                <th style="width: 7%;">Deskripsi</th>
                <th style="width: 5%;">Pihak Terkait</th>
                <th style="width: 4%;">Status Asuransi</th>
                <th style="width: 5%;">Penjelasan Kerugian</th>
                <th style="width: 4.5%;">Nilai Kerugian</th>

                <!-- Kolom tambahan -->
                <th style="width: 4%;">Kejadian Berulang</th>
                <th style="width: 4%;">Frekuensi Kejadian</th>
                <th style="width: 6%;">Mitigasi Yang Direncanakan</th>
                <th style="width: 6%;">Realisasi Mitigasi</th>
                <th style="width: 6%;">Perbaikan Mendatang</th>
                <th style="width: 4.5%;">Nilai Premi</th>
                <th style="width: 4.5%;">Nilai Klaim</th>
                <th style="width: 4%;">Realisasi (%)</th>
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
                <td class="nilai-column">{{ $item['nilai_kerugian_formatted'] }}</td>

                <!-- Data kolom tambahan -->
                <td class="text-center">{{ $item['kejadian_berulang'] ?? '' }}</td>
                <td class="text-center">{{ $item['frekuensi_kejadian'] ?? '' }}</td>
                <td class="wrap-text">{{ $item['mitigasi_yang_direncanakan'] ?? '' }}</td>
                <td class="wrap-text">{{ $item['realisasi_mitigasi'] ?? '' }}</td>
                <td class="wrap-text">{{ $item['perbaikan_mendatang'] ?? '' }}</td>
                <td class="nilai-column">{{ $item['nilai_premi_formatted'] ?? '' }}</td>
                <td class="nilai-column">{{ $item['nilai_klaim_formatted'] ?? '' }}</td>
                <td class="text-center">{{ $item['realization_percentage'] ?? '' }}</td>
                {{-- <td class="text-center">{{ strtoupper($item['type'] ?? '') }}</td> --}}
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
