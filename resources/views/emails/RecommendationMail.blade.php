<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>[SEMESTA-NOTIFICATION] Informasi Rekomendasi Baru</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .header { background-color: #097b44; color: white; padding: 10px; }
        .footer { background-color: #f1f1f1; color: #555; padding: 10px; text-align: center; font-size: 12px; }
        .content { background-color: white; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
<<<<<<< HEAD
        <p style="text-align: left;"><h1>Yth {{ $risk_owner ?? 'Semesta User' }},</h1></p>
=======
        <p style="text-align: left;"><h1>Yth {{ $data->risk_owner ?? 'Semesta User' }},</h1></p>
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        <p style="text-align: right;">{{ date('d-m-Y') }}</p>
    </div>

    <div class="content">
        <p>
            Bersama ini kami sampaikan ada rekomendasi baru, terkait Rencana Investasi anda :
        </p>
        <h1>Informasi Investasi:</h1>
        <ul>
            <li>Erkap ID: {{ $data->erkap_id }}</li>
            <li>Nama Investasi: {{ $data->nama_investasi }}</li>
            <li>Kategori Investasi: {{ $data->kategori_investasi }}</li>
            <li>Tahun: {{ $data->tahun }}</li>
        </ul>

        <h1>Rekomendasi:</h1> {{ $data->rekomendasi }}

<<<<<<< HEAD
=======
        <hr>
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
        <p>
            Apabila terdapat pertanyaan lebih lanjut terkait rekomendasi yang diberikan, 
            Anda dapat menghubungi kami melalui Tim IT dan Tim MR.<br/>
            Demikian kami sampaikan, atas perhatian dan kerja sama Bapak/Ibu kami ucapkan terima kasih.
        </p>
    </div>

    <p>Hormat kami,<br /> Team Manajemen Risiko</p>
    <div class="footer">
        &copy; {{ date('Y') }} PT. Kawasan Berikat Nusantara, All rights reserved.
    </div>
</body>
</html>

