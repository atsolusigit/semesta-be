<!DOCTYPE html>
<html>
<head>
    <title>Laporan Risiko</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 20px;
        }

        body {
            font-family: sans-serif;
            font-size: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            word-wrap: break-word;
        }

        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: top;
        }

        .info {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <h2>Laporan Risiko</h2>
    <div class="info">
        <p><strong>Kode Risiko:</strong> {{ $header->riskCode->name ?? '-' }}</p>
        <p><strong>Departemen:</strong> {{ $header->department->name ?? '-' }}</p>
        <p><strong>Tahun:</strong> {{ $header->year ?? '-' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Header ID</th>
                <th>Tahun</th>
                <th>Department</th>
                <th>Bulan</th>
                <th>Status Risiko</th>
                <th>Start Date</th>
                <th>Expired Date</th>
                <th>Risk Code</th>
                <th>Peristiwa Risiko</th>
                <th>Penyebab Risiko</th>
                <th>Dampak Risiko</th>
                <th>Kontrol Internal</th>
                <th>Target 1 Tahun Notes</th>
                <th>Target Kuantitatif 1 Tahun</th>
                <th>Realisasi Kuantitatif</th>
                <th>Biaya Perlakuan Risiko</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($header->monthlyData as $item)
                <tr>
                    <td>{{ $header->id ?? '-' }}</td>
                    <td>{{ $header->year ?? '-' }}</td>
                    <td>{{ $header->department->name ?? '-' }}</td>
                    <td>{{ \Carbon\Carbon::createFromDate(null, $item->month, 1)->locale('id')->isoFormat('MMMM') }}</td>
                    <td>{{ $item->status_risiko ?? '-' }}</td>
                    <td>{{ $item->start_date ? \Carbon\Carbon::parse($item->start_date)->format('d-m-Y') : '-' }}</td>
                    <td>{{ $item->expired_date ? \Carbon\Carbon::parse($item->expired_date)->format('d-m-Y') : '-' }}</td>
                    <td>{{ $header->riskCode->name ?? '-' }}</td>
                    <td>{{ $header->peristiwa_risiko ?? '-' }}</td>
                    <td>{{ $header->penyebab_risiko ?? '-' }}</td>
                    <td>{{ $header->dampak_risiko ?? '-' }}</td>
                    <td>{{ $header->internal_control ?? '-' }}</td>
                    <td>{{ $header->target_satu_tahun_notes ?? '-' }}</td>
                    <td>{{ $header->target_quantitative_satu_tahun ?? 0 }}</td>
                    <td>{{ $item->realization_quantitative ?? 0 }}</td>
                    <td>{{ $header->biaya_perlakuan_risiko ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
