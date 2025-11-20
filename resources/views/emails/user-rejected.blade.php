<!DOCTYPE html>
<html>
<head>
    <title>Akun Ditolak</title>
</head>
<body>
    <h2>Maaf, {{ $user->name }}</h2>
    <p>Akun Anda dengan email <strong>{{ $user->email }}</strong> telah ditolak oleh admin.</p>
    <p>Jika Anda merasa ini adalah kesalahan, silakan hubungi administrator.</p>
    <p>Terima kasih.</p>
</body>
</html>
