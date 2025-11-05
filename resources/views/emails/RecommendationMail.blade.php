<!DOCTYPE html>
<html>
<head>
    <title>[SEMESTA-NOTIFICATION] Rekomendasi Baru</title>
</head>
<body>
    <h1>Ada rekomendasi baru</h1>
    <p>Dear {{ $riskInvestasi->createdBy ?? 'Semesta User' }},</p>
    
    <h2>Informasi Investasi:</h2>
    <ul>
        <li>Erkap ID: {{ $erkapID }}</li>
        <li>Nama Investasi: {{ $namaInvestasi }}</li>
        <li>Tahun: {{ $tahun }}</li>
    </ul>

    <h2>Rekomendasi:</h2>
    <p>{{ $rekomendasi }}</p>

    <p>Best regards</p>
    <p>ManRisk Team</p>
    <p>PT. Kawasan Berikat Nusantara</p>
</body>
</html>