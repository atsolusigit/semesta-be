<!DOCTYPE html>
<html>
<head>
    <title>Risk Report PDF</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        /* ==== Halaman & Font ==== */
        @page {
            size: A4 landscape;
            margin: 15px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            margin: 0;
            padding: 0;
        }

        .page-break { page-break-before: always; }

        .header-section {
            text-align: center;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .header-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .header-subtitle {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .header-info {
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        /* ==== Tabel Umum ==== */
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;        /* stabil di PDF */
            word-wrap: break-word;
            margin-top: 10px;
            page-break-inside: auto;
        }

        thead { display: table-header-group; }   /* repeat header tiap halaman PDF */
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; page-break-after: auto; }

        th, td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
            vertical-align: top;
            font-size: 7px;
            line-height: 1.25;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        th {
            background-color: #d8e4bc;
            font-weight: bold;
            font-size: 6px; /* kecil agar header tidak mudah pecah */
        }

        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* ==== Risk Register ==== */
        .risk-register-table th {
            height: 40px;
            vertical-align: middle;
        }

        /* Warnai otomatis sel level risiko berdasarkan data-level (tanpa ubah HTML) */
        .risk-level-cell { font-weight: bold; }
        .risk-level-cell[data-level="Low"]               { background-color: #00B050; color: #000; }
        .risk-level-cell[data-level="Low to Moderate"]   { background-color: #92D050; color: #000; }
        .risk-level-cell[data-level="Moderate"]          { background-color: #FFFF00; color: #000; }
        .risk-level-cell[data-level="Moderate to High"]  { background-color: #FFC000; color: #000; }
        .risk-level-cell[data-level="High"]              { background-color: #FF0000; color: #fff; }

        /* ==== Monitoring ==== */
        .monitoring-table th { height: 35px; }

        /* ==== Heatmap ==== */
        .heatmap-wrapper { display: flex; gap: 16px; align-items: flex-start; }
        .heatmap-main { flex: 1 1 auto; }
        .heatmap-legend { width: 220px; }

        .heatmap-table {
            margin: 10px 0;
            width: 100%;
        }

        /* Head vertikal "PROBABILITAS" — tetap pakai writing-mode,
           plus fallback transform agar kompatibel dompdf/wkhtmltopdf */
        .heatmap-table thead tr:first-child th:first-child {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);   /* fallback bila writing-mode tidak didukung */
            white-space: nowrap;
            width: 26px;                 /* kolom sempit sesuai contoh */
            padding: 6px 2px !important;
            text-align: center;
            vertical-align: middle;
        }

        .vertical-text {
            writing-mode: vertical-rl;
            text-orientation: upright;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            padding: 10px 5px;
            width: 30px;
            background-color: #d8e4bc;
            border: 1px solid #000;
            vertical-align: middle;
            letter-spacing: 2px;
        }

        .alternative-vertical {
            writing-mode: vertical-lr;
            text-orientation: mixed;
            transform: rotate(180deg);
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            padding: 10px 5px;
            width: 30px;
            background-color: #d8e4bc;
            border: 1px solid #000;
            vertical-align: middle;
        }
        /* Baris kedua header (angka dampak 1–5) */
        .heatmap-table thead tr:nth-child(2) th {
            height: 20px;
            font-size: 8px;
            vertical-align: middle;
        }

        /* Label probabilitas di kolom pertama body */
        .heatmap-table tbody tr td:first-child {
            background-color: #d8e4bc;
            font-size: 9px;
            text-align: left;
            padding: 4px 6px;
            width: 160px;                /* ruang cukup untuk teks label panjang */
            vertical-align: middle;
        }

        /* Sel matriks heatmap */
        .heatmap-table td:not(:first-child) {
            height: 60px;
            text-align: center;
            vertical-align: middle;
            padding: 4px 2px;
        }

        .heatmap-cell {
            width: 80px;
            height: 60px;
            text-align: center;
            vertical-align: middle;
            font-weight: bold;
            font-size: 8px;
        }

        .risk-low { background-color: #00B050; }
        .risk-low-moderate { background-color: #92D050; }
        .risk-moderate { background-color: #FFFF00; }
        .risk-moderate-high { background-color: #FFC000; }
        .risk-high { background-color: #FF0000; }

        /* Teks kecil di dalam heatmap (nama level & skor) */
        .heatmap-table .risk-name { font-size: 8px; font-weight: bold; line-height: 1.1; }
        .heatmap-table .risk-score { font-size: 14px; font-weight: bold; line-height: 1.1; }

        /* Bulet jumlah risiko (kalau ada) – untuk span bulet inline yang sudah ada */
        .heatmap-table .risk-count { margin-top: 2px; display: inline-flex; gap: 3px; align-items: center; }
        .heatmap-table .risk-count span { font-weight: bold; }

        /* Legend */
        .legend-table { margin: 0; width: 100%; }
        .legend-table th, .legend-table td { font-size: 8px; height: 20px; }
        .legend-cell { width: 150px; height: 25px; }

        .probability-label { text-align: left; padding-left: 5px; font-size: 7px; }

        .impact-header {
            background-color: #d8e4bc;
            font-weight: bold;
            text-align: center;
            height: 25px;
        }

        /* ==== Lebar kolom Risk Register sesuai nama asli ==== */
        .col-no { width: 3%; }
        .col-code { width: 8%; }
        .col-type { width: 8%; }
        .col-target { width: 10%; }
        .col-event { width: 12%; }
        .col-cause { width: 12%; }
        .col-impact { width: 12%; }
        .col-level { width: 4%; }
        .col-control { width: 15%; }
        .col-amount { width: 10%; }
        .col-percent { width: 8%; }
        .col-treatment { width: 15%; }
        .col-status { width: 6%; }

        .rotate {
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            width: 1.5em;
        }

        .rotate div {
            -moz-transform: rotate(-90.0deg);  /* FF3.5+ */
            -o-transform: rotate(-90.0deg);  /* Opera 10.5 */
            -webkit-transform: rotate(-90.0deg);  /* Saf3.1+, Chrome */
            filter:  progid:DXImageTransform.Microsoft.BasicImage(rotation=0.083);  /* IE6,IE7 */
            -ms-filter: "progid:DXImageTransform.Microsoft.BasicImage(rotation=0.083)"; /* IE8 */
            margin-left: -10em;
            margin-right: -10em;
        }
    </style>
</head>
<body>
    <!-- HALAMAN 1: RISK REGISTER -->
    <div class="header-section">
        <div class="header-title">KERTAS KERJA RISK REGISTER</div>
        <div class="header-subtitle">PT. KAWASAN BERIKAT NUSANTARA</div>
        <div class="header-info">UNIT KERJA : {{ $headers->first()->department->name ?? 'TIDAK DIKETAHUI' }}</div>
        <div class="header-info">PERIODE : BULAN {{ strtoupper($monthName) }} {{ $year }}</div>
    </div>

    <table class="risk-register-table">
        <thead>
            <tr>
                <th rowspan="2" class="col-no">NO</th>
                <th rowspan="2" class="col-code">KODE RISIKO</th>
                <th rowspan="2" class="col-type">JENIS RISIKO</th>
                <th rowspan="2" class="col-target">SASARAN</th>
                <th rowspan="2" class="col-event">PERISTIWA RISIKO</th>
                <th rowspan="2" class="col-cause">PENYEBAB RISIKO</th>
                <th rowspan="2" class="col-impact">DAMPAK RISIKO</th>
                <th colspan="4">INHERENT RISK</th>
                <th rowspan="2" class="col-control">INTERNAL CONTROL</th>
                <th rowspan="2" class="col-amount">TARGET s/d BULAN {{ strtoupper($monthName) }}</th>
                <th rowspan="2" class="col-amount">REALISASI s/d BULAN {{ strtoupper($monthName) }}</th>
                <th rowspan="2" class="col-percent">% s/d BULAN {{ strtoupper($monthName) }}</th>
                <th colspan="4">RESIDUAL RISK</th>
                <th rowspan="2" class="col-amount">TARGET 1 TAHUN</th>
                <th rowspan="2" class="col-amount">REALISASI S/D BULAN {{ strtoupper($monthName) }}</th>
                <th colspan="4">RESIDUAL RISK</th>
                <th rowspan="2" class="col-treatment">PERLAKUAN RISIKO (MITIGASI)</th>
                <th rowspan="2" class="col-amount">BIAYA PERLAKUAN RISIKO</th>
                <th colspan="4">RESIDUAL TARGET</th>
                <th rowspan="2" class="col-status">STATUS RISIKO</th>
            </tr>
            <tr>
                <th class="col-level">DAMPAK</th>
                <th class="col-level">KEMUNGKINAN</th>
                <th class="col-level">POSISI RISIKO</th>
                <th class="col-level">LEVEL RISIKO</th>
                <th class="col-level">DAMPAK</th>
                <th class="col-level">KEMUNGKINAN</th>
                <th class="col-level">POSISI RISIKO</th>
                <th class="col-level">LEVEL RISIKO</th>
                <th class="col-level">DAMPAK</th>
                <th class="col-level">KEMUNGKINAN</th>
                <th class="col-level">POSISI RISIKO</th>
                <th class="col-level">LEVEL RISIKO</th>
                <th class="col-level">DAMPAK</th>
                <th class="col-level">KEMUNGKINAN</th>
                <th class="col-level">POSISI RISIKO</th>
                <th class="col-level">LEVEL RISIKO</th>
            </tr>
        </thead>
        <tbody>
            @foreach($riskRegisterData as $row)
            <tr>
                <td class="text-center">{{ $row['no'] }}</td>
                <td class="text-center">{{ $row['risk_code'] }}</td>
                <td class="text-left">{{ $row['jenis_risiko'] }}</td>
                <td class="text-left">{{ $row['sasaran'] }}</td>
                <td class="text-left">{{ $row['peristiwa_risiko'] }}</td>
                <td class="text-left">{{ $row['penyebab_risiko'] }}</td>
                <td class="text-left">{{ $row['dampak_risiko'] }}</td>
                <td class="text-center">{{ $row['inherent_risk_level_dampak'] }}</td>
                <td class="text-center">{{ $row['inherent_risk_level_kemungkinan'] }}</td>
                <td class="text-center">{{ $row['inherent_risk_posisi_risiko'] }}</td>
                <td class="text-center risk-level-cell" data-level="{{ $row['inherent_risk_level_risiko'] }}">{{ $row['inherent_risk_level_risiko'] }}</td>
                <td class="text-left">{{ $row['internal_control'] }}</td>
                <td class="text-center">{{ $row['target_bulan'] }}</td>
                <td class="text-center">{{ $row['realisasi_bulan'] }}</td>
                <td class="text-center">{{ $row['percentage'] }}</td>
                <td class="text-center">{{ $row['residual_risk_level_dampak'] }}</td>
                <td class="text-center">{{ $row['residual_risk_level_kemungkinan'] }}</td>
                <td class="text-center">{{ $row['residual_risk_posisi_risiko'] }}</td>
                <td class="text-center risk-level-cell" data-level="{{ $row['residual_risk_level_risiko'] }}">{{ $row['residual_risk_level_risiko'] }}</td>
                <td class="text-left">{{ $row['target_1_tahun'] }}</td>
                <td class="text-center">{{ $row['realisasi_duplicate'] }}</td>
                <td class="text-center">{{ $row['residual_target_level_dampak'] }}</td>
                <td class="text-center">{{ $row['residual_target_level_kemungkinan'] }}</td>
                <td class="text-center">{{ $row['residual_target_posisi_risiko'] }}</td>
                <td class="text-center risk-level-cell" data-level="{{ $row['residual_target_level_risiko'] }}">{{ $row['residual_target_level_risiko'] }}</td>
                <td class="text-left">{{ $row['perlakuan_risiko'] }}</td>
                <td class="text-right">{{ $row['biaya_perlakuan'] }}</td>
                <td class="text-center">{{ $row['residual_target_level_dampak'] }}</td>
                <td class="text-center">{{ $row['residual_target_level_kemungkinan'] }}</td>
                <td class="text-center">{{ $row['residual_target_posisi_risiko'] }}</td>
                <td class="text-center risk-level-cell" data-level="{{ $row['residual_target_level_risiko'] }}">{{ $row['residual_target_level_risiko'] }}</td>
                <td class="text-center">{{ $row['status_risiko'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

  <!-- HALAMAN 2: MONITORING RISIKO -->
@php
    use Carbon\Carbon;

    // Pastikan dapat angka bulan
    try {
        $monthNumber = Carbon::parse("1 {$monthName} {$year}")->month;
    } catch (\Exception $e) {
        $monthNumber = 1; // default Januari
    }

    // Hitung triwulan
    if ($monthNumber >= 1 && $monthNumber <= 3) {
        $triwulan = 'TRIWULAN PERTAMA';
    } elseif ($monthNumber >= 4 && $monthNumber <= 6) {
        $triwulan = 'TRIWULAN KEDUA';
    } elseif ($monthNumber >= 7 && $monthNumber <= 9) {
        $triwulan = 'TRIWULAN KETIGA';
    } else {
        $triwulan = 'TRIWULAN KEEMPAT';
    }
@endphp

<div class="page-break" style="display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; padding: 20px 0;">
    <div class="header-section" style="text-align: center; margin-bottom: 20px;">
        <div class="header-title">KERTAS KERJA MONITORING RISIKO</div>
        <div class="header-subtitle">PT. KAWASAN BERIKAT NUSANTARA</div>
        <div class="header-info">UNIT KERJA : {{ $headers->first()->department->name ?? 'TIDAK DIKETAHUI' }}</div>
        <div class="header-info">PERIODE : BULAN {{ strtoupper($monthName) }} {{ $year }}</div>
    </div>

    <table class="monitoring-table" style="margin: 0 auto;">
        <thead>
            <tr>
                <th rowspan="3" class="col-no" style="vertical-align: middle;">NO</th>
                <th rowspan="3" class="col-code" style="vertical-align: middle;">KODE RISIKO</th>
                <th rowspan="3" class="col-type" style="vertical-align: middle;">JENIS RISIKO</th>
                <th rowspan="3" class="col-event" style="vertical-align: middle;">PERISTIWA RISIKO</th>
                <th rowspan="3" class="col-cause" style="vertical-align: middle;">PENYEBAB RISIKO</th>
                <th colspan="4">WAKTU PELAKSANAAN</th>
                <th rowspan="3" class="col-percent" style="vertical-align: middle;">% s/d BULAN {{ strtoupper($monthName) }}</th>
                <th rowspan="3" class="col-percent" style="vertical-align: middle;">% TARGET TAHUN {{ $year }}</th>
                <th rowspan="3" class="col-amount" style="vertical-align: middle;">BIAYA PERLAKUAN RISIKO</th>
                <th colspan="4">RESIDUAL TARGET RISK</th>
                <th rowspan="3" class="col-treatment" style="vertical-align: middle;">EVALUASI PERLAKUAN RISIKO</th>
            </tr>
            <tr>
                <th colspan="4">TAHUN {{ $year }}<br>{{ $triwulan }}<br>BULAN {{ strtoupper($monthName) }}</th>
                <th rowspan="2" class="col-level" style="vertical-align: middle;">LEVEL DAMPAK</th>
                <th rowspan="2" class="col-level" style="vertical-align: middle;">LEVEL KEMUNGKINAN</th>
                <th rowspan="2" class="col-level" style="vertical-align: middle;">POSISI RISIKO</th>
                <th rowspan="2" class="col-level" style="vertical-align: middle;">LEVEL RISIKO</th>
            </tr>
            <tr>
                <th class="col-amount">TARGET s/d BULAN {{ strtoupper($monthName) }}</th>
                <th class="col-amount">REALISASI s/d BULAN {{ strtoupper($monthName) }}</th>
                <th class="col-amount">TARGET 1 TAHUN</th>
                <th class="col-amount">REALISASI s/d BULAN {{ strtoupper($monthName) }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($monitoringData as $row)
            <tr>
                <td class="text-center">{{ $row['no'] }}</td>
                <td class="text-center">{{ $row['risk_code'] }}</td>
                <td class="text-left">{{ $row['jenis_risiko'] }}</td>
                <td class="text-left">{{ $row['peristiwa_risiko'] }}</td>
                <td class="text-left">{{ $row['penyebab_risiko'] }}</td>
                <td class="text-center">{{ $row['target_bulan'] }}</td>
                <td class="text-center">{{ $row['realisasi_bulan'] }}</td>
                <td class="text-left">{{ $row['target_1_tahun'] }}</td>
                <td class="text-center">{{ $row['realisasi_duplicate'] }}</td>
                <td class="text-center">{{ $row['percentage_bulan'] }}</td>
                <td class="text-center">{{ $row['percentage_tahun'] }}</td>
                <td class="text-left">{{ $row['biaya_perlakuan'] }}</td>
                <td class="text-center">{{ $row['level_dampak'] }}</td>
                <td class="text-center">{{ $row['level_kemungkinan'] }}</td>
                <td class="text-center">{{ $row['posisi_risiko'] }}</td>
                <td class="text-center risk-level-cell" data-level="{{ $row['level_risiko'] }}">{{ $row['level_risiko'] }}</td>
                <td class="text-left">{{ $row['evaluasi_perlakuan'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- HALAMAN 3: PETA RISIKO (HEATMAP) -->
<div class="page-break">
    <div class="header-section">
        <div class="header-title">PETA RISIKO</div>
        <div class="header-subtitle">PT. KAWASAN BERIKAT NUSANTARA</div>
        <div class="header-info">PERIODE : {{ strtoupper($monthName) }} {{ $year }}</div>
    </div>

    <div>
        <table style="border-collapse: collapse; text-align: center; font-size: 9px; table-layout: fixed; width: 100%;">
            <tbody>
                @php
                    $probabilityLabels = [
                        5 => 'Hampir pasti terjadi [5]',
                        4 => 'Sangat mungkin terjadi [4]',
                        3 => 'Bisa terjadi [3]',
                        2 => 'Jarang terjadi [2]',
                        1 => 'Sangat jarang terjadi [1]'
                    ];
                    $impactLabels = [
                        1 => 'Sangat Rendah [1]',
                        2 => 'Rendah [2]',
                        3 => 'Menengah [3]',
                        4 => 'Tinggi [4]',
                        5 => 'Sangat Tinggi [5]'
                    ];
                    $heatmapMatrix = [];
                    foreach($heatmapData['structure'] as $item) {
                        $heatmapMatrix[$item->kemungkinan][$item->dampak] = $item->result;
                    }
                @endphp

                @foreach([5, 4, 3, 2, 1] as $prob)
                    <tr>
                        @if($prob == 5)
                            <td rowspan="5" class="rotate" style="
                                background-color: #ffffff;
                                border: 1px solid #000;
                                text-align: center;
                                vertical-align: middle;
                                padding: 0;
                                width: 4%;
                                font-weight: bold;
                                font-size: 10px;

                            ">
                                <div >PROBABILITAS</div>
                            </td>
                        @endif
                        <td style="background-color: #ffffff; border: 1px solid #000; font-size: 9px; text-align: left; padding: 8px; width: 13%;">{{ $probabilityLabels[$prob] }}</td>
                        @for($impact = 1; $impact <= 5; $impact++)
                            @php
                                $riskScore = $heatmapMatrix[$prob][$impact] ?? 0;
                                $riskLevel = '';
                                $bgColor = '';

                                // Logika untuk menentukan warna berdasarkan skor risiko
                                if($riskScore >= 1 && $riskScore <= 5) {
                                    $riskLevel = 'Low';
                                    $bgColor = '#00B050';
                                } elseif($riskScore >= 6 && $riskScore <= 11) {
                                    $riskLevel = 'Low to Moderate';
                                    $bgColor = '#92D050';
                                } elseif($riskScore >= 12 && $riskScore <= 15) {
                                    $riskLevel = 'Moderate';
                                    $bgColor = '#FFFF00';
                                } elseif($riskScore >= 16 && $riskScore <= 19) {
                                    $riskLevel = 'Moderate to High';
                                    $bgColor = '#FFC000';
                                } elseif($riskScore >= 20 && $riskScore <= 25) {
                                    $riskLevel = 'High';
                                    $bgColor = '#FF0000';
                                }

                                $key = $prob . '_' . $impact;
                                $inherentCount = $heatmapData['inherent_counts'][$key] ?? 0;
                                $currentCount = $heatmapData['residual_current_counts'][$key] ?? 0;
                                $targetCount = $heatmapData['residual_target_counts'][$key] ?? 0;
                            @endphp
                            <td style="background-color: {{ $bgColor }}; border: 1px solid #000; padding: 3px; text-align: center; vertical-align: middle; width: 80px; height: 60px;">
                                <div style="font-weight: bold; font-size: 8px; line-height: 1.1; margin-bottom: 2px;">{{ $riskLevel }}</div>
                                <div style="font-size: 11px; font-weight: bold; margin-bottom: 3px;">{{ $riskScore }}</div>
                                @if($inherentCount || $currentCount || $targetCount)
                                    <div style="text-align: center;">
                                        @if($inherentCount)
                                            <span style="display:inline-block; background-color:#0070C0; color:white; border-radius:50%; width:14px; height:14px; font-size:8px; line-height:14px; text-align:center; margin:1px;">{{ $inherentCount }}</span>
                                        @endif
                                        @if($currentCount)
                                            <span style="display:inline-block; background-color:#7F7F7F; color:white; border-radius:50%; width:14px; height:14px; font-size:8px; line-height:14px; text-align:center; margin:1px;">{{ $currentCount }}</span>
                                        @endif
                                        @if($targetCount)
                                            <span style="display:inline-block; background-color:#7030A0; color:white; border-radius:50%; width:14px; height:14px; font-size:8px; line-height:14px; text-align:center; margin:1px;">{{ $targetCount }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endforeach

                <tr>
                    <td style="background-color: #ffffff; border: none; width: 20px;"></td>
                    <td style="background-color: #ffffff; border: none; width: 140px;"></td>
                    <td style="border: 1px solid #000; text-align: center; font-size: 8px; padding: 4px; width: 80px;">{{ $impactLabels[1] }}</td>
                    <td style="border: 1px solid #000; text-align: center; font-size: 8px; padding: 4px; width: 80px;">{{ $impactLabels[2] }}</td>
                    <td style="border: 1px solid #000; text-align: center; font-size: 8px; padding: 4px; width: 80px;">{{ $impactLabels[3] }}</td>
                    <td style="border: 1px solid #000; text-align: center; font-size: 8px; padding: 4px; width: 80px;">{{ $impactLabels[4] }}</td>
                    <td style="border: 1px solid #000; text-align: center; font-size: 8px; padding: 4px; width: 80px;">{{ $impactLabels[5] }}</td>
                </tr>
                <tr>
                    <td style="background-color: #ffffff; border: none; width: 20px;"></td>
                    <td style="background-color: #ffffff; border: none; width: 140px;"></td>
                    <td colspan="5" style="background-color: #ffffff; border: 1px solid #000; font-weight: bold; text-align: center; font-size: 10px; padding: 4px;">DAMPAK</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="font-size: 12px; margin-top: 20px; display: flex; justify-content: space-between; align-items: flex-start;">

        <table style="border-collapse: collapse; width: 250px; font-size: 12px;">
            <thead>
                <tr>
                    <th style="background-color: #ffffff; border: 1px solid #000;">LEVEL RISIKO</th>
                    <th style="background-color: #ffffff; border: 1px solid #000;">POSISI</th>
                </tr>
            </thead>
            <tbody>
                <tr><td style="background-color: #00B050; border: 1px solid #000;">Low</td><td style="border: 1px solid #000;">1 - 5</td></tr>
                <tr><td style="background-color: #92D050; border: 1px solid #000;">Low to Moderate</td><td style="border: 1px solid #000;">6 - 11</td></tr>
                <tr><td style="background-color: #FFFF00; border: 1px solid #000;">Moderate</td><td style="border: 1px solid #000;">12 - 15</td></tr>
                <tr><td style="background-color: #FFC000; border: 1px solid #000;">Moderate to High</td><td style="border: 1px solid #000;">16 - 19</td></tr>
                <tr><td style="background-color: #FF0000; color:white; border: 1px solid #000;">High</td><td style="border: 1px solid #000;">20 - 25</td></tr>
            </tbody>
        </table>

        <div style="margin-left: 40px; font-size: 9px;">
            <div style="font-weight: bold; font-size: 11px; margin-bottom: 5px;">KETERANGAN :</div>
            <p><span style="display:inline-block; background-color:#0070C0; border-radius:50%; width:8px; height:8px;"></span> : Inherent Risk</p>
            <p><span style="display:inline-block; background-color:#7F7F7F; border-radius:50%; width:8px; height:8px;"></span> : Residual Current Risk (s.d. 31 {{ strtoupper($monthName) }} {{ $year }})</p>
            <p><span style="display:inline-block; background-color:#7030A0; border-radius:50%; width:8px; height:8px;"></span> : Residual Target Risk (Residual saat ini berbanding dengan target {{ $year }})</p>
        </div>
    </div>
</div>

</html>
