<!DOCTYPE html>
<html>
<head>
    <title>User Baru Registrasi</title>
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
        .alert-box {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
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
        .action-button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
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
        <h2>🔔 User Baru Menunggu Persetujuan</h2>

        <div class="alert-box">
            <p><strong>Seorang user baru telah mendaftar dan menunggu persetujuan Anda:</strong></p>
            <ul>
                <li><strong>Nama:</strong> {{ $user->name }}</li>
                <li><strong>Username:</strong> {{ $user->username }}</li>
                <li><strong>Email:</strong> {{ $user->email }}</li>
                <li><strong>Tanggal Registrasi:</strong> {{ $user->created_at->format('d M Y') }}</li>
            </ul>
        </div>

        <p>Silakan login ke sistem untuk menyetujui atau menolak registrasi ini.</p>

        <div class="footer">
            <p>Email ini dikirim secara otomatis dari sistem.</p>
        </div>
    </div>
</body>
</html>
