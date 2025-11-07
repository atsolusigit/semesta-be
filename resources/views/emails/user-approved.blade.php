<!DOCTYPE html>
<html>
<head>
    <title>Akun Disetujui</title>
</head>
<body>
    <h2>Selamat, {{ $user->name }}!</h2>
    <p>Akun Anda dengan email <strong>{{ $user->email }}</strong> telah disetujui oleh admin.</p>
    <p>Anda sekarang dapat login ke sistem menggunakan kredensial yang telah Anda daftarkan.</p>
    <p><strong>Username:</strong> {{ $user->username }}</p>
    <p>Terima kasih telah bergabung.</p>
</body>
</html>
