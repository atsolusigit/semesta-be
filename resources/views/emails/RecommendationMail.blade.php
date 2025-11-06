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
        <li>Erkap ID: {{ $erkap_id }}</li>
        <li>Nama Investasi: {{ $nama_investasi }}</li>
        <li>Tahun: {{ $tahun }}</li>
    </ul>

    <h2>Rekomendasi:</h2> {{ $rekomendasi }}
    

    <p>&nbsp;</p>
    <p>Best regards</p>
    <p>ManRisk Team</p>
    <p>PT. Kawasan Berikat Nusantara</p>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>[SEMESTA-NOTIFICATION] Informasi Rekomendasi Baru</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 10px; text-align: center; }
        .footer { background-color: #f1f1f1; color: #555; padding: 10px; text-align: center; font-size: 12px; }
        .content { background-color: white; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <p>Yth {{ $riskInvestasi->createdBy ?? 'Semesta User' }},</p>
        <p style="text-align: right;">{{ date('D-M-Y') }}</p>
    </div>

    <div class="content">
        <p>
            Bersama ini kami sampaikan ada rekomendasi baru, terkait Rencana Investasi anda :
        </p>
        <h1>Informasi Investasi:</h1>
        <ul>
            <li>Erkap ID: {{ $erkap_id }}</li>
            <li>Nama Investasi: {{ $nama_investasi }}</li>
            <li>Nama Investasi: {{ $nama_investasi }}</li>
            <li>Tahun: {{ $tahun }}</li>
        </ul>

        <h1>Rekomendasi:</h1> {{ $rekomendasi }}

        <p>
            Apabila terdapat pertanyaan lebih lanjut terkait rekomendasi yang diberikan, 
            Anda dapat menghubungi kami melalui Tim IT dan Tim MR.
        </p>
        <p>
            Demikian kami sampaikan, atas perhatian dan kerja sama Bapak/Ibu kami ucapkan terima kasih.
        </p>
    </div>

    <div class="footer">
        Hormat kami,
        &copy; {{ date('Y') }} PT. Kawasan Berikat Nusantara, All rights reserved.
    </div>
</body>
</html>

