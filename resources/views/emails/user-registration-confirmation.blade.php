<!DOCTYPE html>
<html>
<head>
    <title>Registrasi Berhasil</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        h2 {
            color: #2c3e50;
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 5px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Terima kasih, {{ $user->name }}!</h2>
        <p>Registrasi Anda telah berhasil dengan detail sebagai berikut:</p>

        <div class="info-box">
            <ul>
                <li><strong>Username:</strong> {{ $user->username }}</li>
                <li><strong>Email:</strong> {{ $user->email }}</li>
            </ul>
        </div>

        <p>Akun Anda saat ini <strong>menunggu persetujuan</strong> dari administrator.</p>
        <p>Anda akan menerima email notifikasi setelah akun Anda disetujui atau ditolak oleh admin.</p>

        <p>Terima kasih telah mendaftar.</p>

        <div class="footer">
            <p>Email ini dikirim secara otomatis, mohon tidak membalas email ini.</p>
        </div>
    </div>
</body>
</html>
