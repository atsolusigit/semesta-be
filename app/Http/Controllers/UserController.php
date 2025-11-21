<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\MstRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use App\Helpers\encrypt_decrypt_db;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Helpers\check_validation;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Mail\UserApprovedMail;
use App\Mail\UserRejectedMail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
  public function index(Request $request)
{
    $search = $request->input('search');
    $perPage = $request->input('per_page', 10);
    $authUser = auth()->user();

    // Cek apakah user yang login ada
    if (!$authUser) {
        return response()->json([
            'status' => false,
            'message' => 'Unauthorized access.',
            'data' => []
        ], 401);
    }

    $usersQuery = User::with(['role:id,name', 'department:id,name'])
        ->select('id', 'name', 'username', 'email', 'role_id', 'status', 'department_id')
        ->whereIn('status', [1, 2])
        ->orderBy('id', 'asc');

    // Hanya role 1 yang bisa melihat semua user
    // Role lainnya hanya bisa melihat user dari departemen yang sama
    if ((int) $authUser->role_id !== 1) {
        $usersQuery->where('department_id', $authUser->department_id);
    }

    $users = $usersQuery->get();

    $result = [];

    foreach ($users as $userData) {
        try {
            $name = encrypt_decrypt_db('dec', $userData->name, $userData->id);
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt name user ID {$userData->id}: {$e->getMessage()}");
            $name = null;
        }

        try {
            $username = encrypt_decrypt_db('dec', $userData->username, $userData->id);
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt username user ID {$userData->id}: {$e->getMessage()}");
            $username = null;
        }

        try {
            $email = encrypt_decrypt_db('dec', $userData->email, $userData->id);
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt email user ID {$userData->id}: {$e->getMessage()}");
            $email = null;
        }

        // Filter search manual
        if ($search) {
            $searchLower = strtolower($search);
            if (
                (is_string($name) && strpos(strtolower($name), $searchLower) === false) &&
                (is_string($username) && strpos(strtolower($username), $searchLower) === false)
            ) {
                continue;
            }
        }

        $result[] = [
            'id' => encrypt_decrypt_md5('enc', $userData->id),
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'role_id' => $userData->role_id,
            'department_id' => $userData->department_id,
            'status' => $userData->status,
            'role_name' => $userData->role?->name,
            'department_name' => $userData->department?->name,
        ];
    }

    // Manual pagination untuk array result
    $total = count($result);
    $currentPage = $request->input('page', 1);
    $offset = ($currentPage - 1) * $perPage;
    $paginatedResult = array_slice($result, $offset, $perPage);
    $lastPage = (int) ceil($total / $perPage);

    // Prepare response dengan pagination
    $responseData = [
        'current_page' => (int) $currentPage,
        'per_page' => (int) $perPage,
        'total' => $total,
        'last_page' => $lastPage,
        'from' => $total > 0 ? $offset + 1 : null,
        'to' => $total > 0 ? min($offset + $perPage, $total) : null,
        'data' => $paginatedResult,
    ];

    return response()->json([
        'status' => true,
        'message' => 'List data user.',
        'data' => $responseData
    ]);
}

public function show(Request $request, $id)
{
    try {
        $id = encrypt_decrypt_md5('dec', $id); // Dekripsi ID menggunakan MD5
    } catch (\Throwable $e) {
        return json(400, 'false', 'invalid_id', 'ID tidak valid atau gagal didekripsi.', []);
    }

    // Validasi ID
    $check = check_validation(['id' => $id], [
        'id' => 'required|numeric|exists:users,id'
    ]);
    if ($check[0] === 1) return $check[1];

    $authUser = auth()->user();

    // Cek apakah user yang login ada
    if (!$authUser) {
        return json(401, 'false', 'unauthorized', 'Unauthorized access.', []);
    }

    // Ambil data user dengan relasi
    $user = User::with(['role:id,name', 'department:id,name'])
        ->select('id', 'name', 'username', 'email', 'role_id', 'status', 'department_id')
        ->find($id);

    if (!$user) {
        return json(404, 'error', 'Not Found', 'User tidak ditemukan', null);
    }

    // Cek akses: hanya role 1 yang bisa melihat semua user
    // Role lainnya hanya bisa melihat user dari departemen yang sama
    if ((int) $authUser->role_id !== 1 && $authUser->department_id !== $user->department_id) {
        return json(403, 'false', 'forbidden', 'Anda tidak memiliki akses untuk melihat user di departemen ini.', []);
    }

    // Dekripsi semua field yang dienkripsi menggunakan encrypt_decrypt_db
    try {
        $name = encrypt_decrypt_db('dec', $user->name, $user->id);
        if (!mb_check_encoding($name, 'UTF-8')) {
            \Log::warning("Name bukan UTF-8 valid untuk user ID {$user->id}");
            $name = null;
        }
    } catch (\Throwable $e) {
        \Log::warning("Gagal decrypt name user ID {$user->id}: {$e->getMessage()}");
        $name = null;
    }

    try {
        $username = encrypt_decrypt_db('dec', $user->username, $user->id);
        if (!mb_check_encoding($username, 'UTF-8')) {
            \Log::warning("Username bukan UTF-8 valid untuk user ID {$user->id}");
            $username = null;
        }
    } catch (\Throwable $e) {
        \Log::warning("Gagal decrypt username user ID {$user->id}: {$e->getMessage()}");
        $username = null;
    }

    try {
        $email = encrypt_decrypt_db('dec', $user->email, $user->id);
        if (!mb_check_encoding($email, 'UTF-8')) {
            \Log::warning("Email bukan UTF-8 valid untuk user ID {$user->id}");
            $email = null;
        }
    } catch (\Throwable $e) {
        \Log::warning("Gagal decrypt email user ID {$user->id}: {$e->getMessage()}");
        $email = null;
    }

    $data = [
        'id' => encrypt_decrypt_md5('enc', $user->id), // Enkripsi ID untuk response
        'name' => $name,
        'username' => $username,
        'email' => $email,
        'role_id' => $user->role_id,
        'department_id' => $user->department_id,
        'status' => $user->status,
        'role_name' => optional($user->role)->name ?? '-',
        'department_name' => optional($user->department)->name ?? '-',
    ];

    return json(200, 'success', 'Success', 'Berhasil menampilkan detail user', [$data]);
}

  public function store(Request $request)
{
    // Cek apakah user yang sedang login memiliki role ID 1 atau 2
    $user = auth()->user();
    $roleCheck = check_role($user, [1, 2]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    DB::beginTransaction();

    $array_validation = [
        'name' => 'required|string|max:255',
        'username' => 'required|string|max:100|unique:users',
        'email' => 'required|email|unique:users,email',
        'password' => [
            'required',
            'string',
            Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
        ],
        'role_id' => 'required|exists:mst_role,id',
        'department_id' => 'required|exists:mst_department,id',
        'status' => ['required', Rule::in(['aktif', 'pasif'])],
    ];

    $validation = check_validation($request->all(), $array_validation);
    if ($validation[0] != 0) {
        return $validation[1];
    }

    try {
        $status = $request->status === 'aktif' ? 1 : 0;
        $fbtk = 'FBTK-' . strtoupper(Str::random(10));

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role_id' => $request->role_id,
            'department_id' => $request->department_id,
            'status' => $status,
            'jtkn' => '',
            'fbtk' => $fbtk,
            'profile_img' => '',
        ]);

        // Enkripsi data menggunakan encrypt_decrypt_db setelah user di buat
        User::where('id', $user->id)->update([
            'name' => DB::raw(encrypt_decrypt_db('enc', $request->name, $user->id)),
            'username' => DB::raw(encrypt_decrypt_db('enc', $request->username, $user->id)),
            'email' => DB::raw(encrypt_decrypt_db('enc', $request->email, $user->id))
        ]);

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'User berhasil ditambahkan.'
        ], 200);

    } catch (\Exception $e) {
        DB::rollback();
        return json(500, false, 'store_failed', 'Terjadi kesalahan saat menyimpan user.', $e->getMessage());
    }
}

   public function update(Request $request, $id)
{
    try {
        $id = encrypt_decrypt_md5('dec', $id); // Dekripsi ID menggunakan MD5
    } catch (\Throwable $e) {
        return json(400, 'false', 'invalid_id', 'ID tidak valid atau gagal didekripsi.', []);
    }

    $user = User::find($id);
    if (!$user) {
        return json(404, 'false', 'not_found', 'User tidak ditemukan.', []);
    }

    // Check role authorization - hanya role 1 dan 2 yang bisa update
    $currentUser = auth()->user();
    $roleCheck = check_role($currentUser, [1, 2]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $currentUserRoleId = $currentUser->role_id;
    $currentUserDepartmentId = $currentUser->department_id;

    // Validasi akses berdasarkan role
    if ($currentUserRoleId == 2) {
        // Role 2 (admin) hanya bisa update user dengan role_id 2 atau 3 dan dari department yang sama
        if (!in_array($user->role_id, [2, 3])) {
            return json(400, 'false', 'forbidden', 'Anda tidak memiliki izin untuk mengubah user dengan role ini.', []);
        }
        if ($user->department_id != $currentUserDepartmentId) {
            return json(400, 'false', 'forbidden', 'Anda tidak memiliki izin untuk mengubah user dari department lain.', []);
        }
    }
    // Role 1 bebas mengupdate siapa saja (tidak ada batasan)

    $array_validation = [
        'name' => 'required|string|max:255',
        'username' => 'required|string|max:100|unique:users,username,' . $id,
        'email' => 'required|email|unique:users,email,' . $id,
        'role_id' => 'required|exists:mst_role,id',
        'department_id' => 'required|exists:mst_department,id',
        'status' => ['required', Rule::in(['aktif', 'pasif'])],
        'password' => 'nullable|string|min:6',
    ];

    // Validasi tambahan untuk role 2
    if ($currentUserRoleId == 2) {
        // Role 2 hanya bisa mengubah ke role_id 2 atau 3
        if (!in_array($request->role_id, [2, 3])) {
            return json(400, 'false', 'forbidden', 'Anda hanya dapat mengubah user menjadi role admin atau role 3.', []);
        }
        // Role 2 hanya bisa mengubah ke department yang sama dengan dirinya
        if ($request->department_id != $currentUserDepartmentId) {
            return json(400, 'false', 'forbidden', 'Anda hanya dapat mengubah user ke department yang sama dengan Anda.', []);
        }
    }

    $validation = check_validation($request->all(), $array_validation);
    if ($validation[0] != 0) {
        return $validation[1];
    }

    try {
        DB::beginTransaction();

        $user->role_id = $request->role_id;
        $user->department_id = $request->department_id;
        $user->status = $request->status === 'aktif' ? 1 : 0;

        // Update password jika dikirim
        if (!empty($request->password)) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        // Enkripsi dan update data yang terenkripsi menggunakan encrypt_decrypt_db
        try {
            $encryptedName = encrypt_decrypt_db('enc', $request->name, $user->id);
            $encryptedUsername = encrypt_decrypt_db('enc', $request->username, $user->id);
            $encryptedEmail = encrypt_decrypt_db('enc', $request->email, $user->id);

            DB::table('users')->where('id', $user->id)->update([
                'name' => DB::raw($encryptedName),
                'username' => DB::raw($encryptedUsername),
                'email' => DB::raw($encryptedEmail)
            ]);
        } catch (\Throwable $e) {
            Log::error("Gagal enkripsi data user ID {$user->id}: {$e->getMessage()}");
            DB::rollback();
            return json(500, 'false', 'encrypt_error', 'Gagal mengenkripsi data.', []);
        }

        DB::commit();

        return json(200, 'true', 'success', 'User berhasil diperbarui.', []);
    } catch (\Exception $e) {
        DB::rollback();
        Log::error("Update user gagal ID {$user->id}: {$e->getMessage()}");

        return json(500, 'false', 'server_error', 'Terjadi kesalahan saat memperbarui user.', [
            'error' => $e->getMessage()
        ]);
    }
}

    public function destroy(Request $request, $id)
{
    $authUser = auth()->user();

    // Cek apakah user yang login memiliki role_id 1 (super admin)
    $roleCheck = check_role($authUser, 1);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    try {
        $id = encrypt_decrypt_md5('dec', $id); // Dekripsi ID menggunakan MD5
    } catch (\Throwable $e) {
        return json(400, 'false', 'invalid_id', 'ID tidak valid atau gagal didekripsi.', []);
    }

    $check = check_validation(['id' => $id], [
        'id' => 'required|numeric|exists:users,id'
    ]);
    if ($check[0] === 1) return $check[1];

    $user = User::find($id);
    if (!$user) {
        return json(404, 'error', 'Not Found', 'User tidak ditemukan', null);
    }

    $user->delete();

    return json(200, 'success', 'Success', 'Berhasil menghapus user', null);
}

    public function approveUser($id)
{
    try {
        $id = encrypt_decrypt_md5('dec', $id);
    } catch (\Throwable $e) {
        return json(400, 'false', 'invalid_id', 'ID tidak valid atau gagal didekripsi.', []);
    }

    $authUser = auth()->user();

    $roleCheck = check_role($authUser, [1, 2]);
    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $user = User::find($id);
    if (!$user) {
        return json(404, 'false', 'not_found', 'User tidak ditemukan.', []);
    }

    if ((int) $authUser->role_id === 2 && $authUser->department_id != $user->department_id) {
        return json(403, 'false', 'forbidden', 'Anda tidak memiliki akses untuk menyetujui user di departemen ini.', []);
    }

    $user->status = 1;
    $user->save();

    // Kirim email approval ke user
    try {
        $userEmail = encrypt_decrypt_db('dec', $user->email, $user->id);
        $userName = encrypt_decrypt_db('dec', $user->name, $user->id);
        $userUsername = encrypt_decrypt_db('dec', $user->username, $user->id);

        $userDataForEmail = (object)[
            'id' => $user->id,
            'name' => $userName,
            'username' => $userUsername,
            'email' => $userEmail,
        ];

        Mail::to($userEmail)->send(new UserApprovedMail($userDataForEmail));
    } catch (\Exception $e) {
        \Log::error('Failed to send approval email: ' . $e->getMessage());
    }

    return json(200, 'true', 'success', 'User berhasil diaktifkan dan email notifikasi telah dikirim.', []);
}

public function rejectUser($id)
{
    try {
        $id = encrypt_decrypt_md5('dec', $id);
    } catch (\Throwable $e) {
        return json(400, 'false', 'invalid_id', 'ID tidak valid atau gagal didekripsi.', []);
    }

    $authUser = auth()->user();

    $roleCheck = check_role($authUser, [1, 2]);
    if ($roleCheck !== true) {
        return $roleCheck;
    }

    $user = User::find($id);
    if (!$user) {
        return json(404, 'false', 'not_found', 'User tidak ditemukan.', []);
    }

    if ((int) $authUser->role_id === 2 && $authUser->department_id != $user->department_id) {
        return json(403, 'false', 'forbidden', 'Anda tidak memiliki akses untuk menolak user di departemen ini.', []);
    }

    // Update status menjadi rejected
    $user->status = 2; // 2 = rejected
    $user->save();

    // Kirim email penolakan
    try {
        $userDataForEmail = (object)[
            'id'       => $user->id,
            'name'     => get_decrypted_name($user),
            'username' => get_decrypted_username($user),
            'email'    => get_decrypted_email($user),
        ];

        Mail::to($userDataForEmail->email)->send(new UserRejectedMail($userDataForEmail));
    } catch (\Exception $e) {
        \Log::error('Failed to send rejection email: ' . $e->getMessage());
    }

    // Setelah email dikirim, hapus user dari database
    try {
        $user->delete();
    } catch (\Throwable $e) {
        \Log::error('Gagal menghapus user yang ditolak: ' . $e->getMessage());
        return json(500, 'false', 'delete_failed', 'User ditolak tetapi gagal dihapus dari database.', []);
    }

    return json(200, 'true', 'success', 'User berhasil ditolak, email notifikasi telah dikirim, dan data user telah dihapus.', []);
}

   public function getPendingUsers(Request $request)
{
    // Ambil user yang sedang login untuk keperluan pembatasan data berdasarkan role dan departement
    $authUser = auth()->user();

    // Cek apakah user yang login memiliki role_id 1 atau 2
    // Jika bukan, return response unauthorized
    $roleCheck = check_role($authUser, [1, 2]);

    if ($roleCheck !== true) {
        return $roleCheck;
    }

    // Ambil kata kunci pencarian (search) dari request jika ada
    $search = $request->input('search');

    // Query awal: ambil user yang berstatus pending (status = 0)
    // Sertakan relasi role (id & name) dan department (id & name) untuk ditampilkan di hasil
    $usersQuery = User::with([
            'role:id,name',
            'department:id,name' // relasi untuk ambil nama department
        ])
        ->select('id', 'name', 'username', 'email', 'role_id', 'department_id', 'status')
        ->where('status', 0)
        ->latest('id'); // urutkan dari ID terbaru

    // Jika role user adalah 2 → hanya bisa melihat user dari departement yang sama
    // Role 1 bisa melihat semua data
    if ($authUser && (int) $authUser->role_id === 2) {
        $usersQuery->where('department_id', $authUser->department_id);
    }

    // Eksekusi query dan ambil hasilnya
    $users = $usersQuery->get();

    // Array penampung hasil akhir
    $result = [];

    foreach ($users as $user) {
        // Dekripsi name (jika gagal atau encoding tidak valid → null)
        try {
            $name = encrypt_decrypt_db('dec', $user->name, $user->id);
            if (!mb_check_encoding($name, 'UTF-8')) {
                \Log::warning("Name bukan UTF-8 valid untuk user ID {$user->id}");
                $name = null;
            }
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt name user ID {$user->id}: {$e->getMessage()}");
            $name = null;
        }

        // Dekripsi username (jika gagal atau encoding tidak valid → null)
        try {
            $username = encrypt_decrypt_db('dec', $user->username, $user->id);
            if (!mb_check_encoding($username, 'UTF-8')) {
                \Log::warning("Username bukan UTF-8 valid untuk user ID {$user->id}");
                $username = null;
            }
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt username user ID {$user->id}: {$e->getMessage()}");
            $username = null;
        }

        // Dekripsi email (jika gagal atau encoding tidak valid → null)
        try {
            $email = encrypt_decrypt_db('dec', $user->email, $user->id);
            if (!mb_check_encoding($email, 'UTF-8')) {
                \Log::warning("Email bukan UTF-8 valid untuk user ID {$user->id}");
                $email = null;
            }
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt email user ID {$user->id}: {$e->getMessage()}");
            $email = null;
        }

        // Lewati user jika name kosong atau null
        if (is_null($name) || $name === '') {
            continue;
        }

        // Filter berdasarkan search jika ada
        // Pencarian dilakukan di kolom name dan username yang sudah didekripsi
        if ($search) {
            $searchLower = strtolower($search);
            if (
                (is_string($name) && strpos(strtolower($name), $searchLower) === false) &&
                (is_string($username) && strpos(strtolower($username), $searchLower) === false)
            ) {
                continue; // skip user jika tidak cocok dengan search
            }
        }

        $result[] = [
            'id'              => encrypt_decrypt_md5('enc', $user->id),
            'name'            => $name,
            'username'        => $username,
            'email'           => $email,
            'role_id'         => $user->role_id,
            'role_name'       => $user->role->name ?? '-',
            'department_id'   => $user->department_id,
            'department_name' => $user->department->name ?? '-',
            'status'          => $user->status,
        ];
    }

    // Kirim response JSON sukses berisi data user pending
    return json(200, 'success', 'Success', 'Berhasil menampilkan user dengan status pending', $result);
}

   public function updateProfile(Request $request)
{
    $user = auth()->user();

    $validation = check_validation($request->all(), [
        'name' => 'required|string|max:255',
        'username' => 'required|string|max:255|unique:users,username,' . $user->id,
        'email' => 'nullable|email:rfc,dns|max:255|unique:users,email,' . $user->id,
        'nip' => 'nullable|string|max:100',
        'phone_number' => 'nullable|string|max:100',
        'gender' => 'nullable|in:male,female,other',
        'profile_img' => 'nullable|string', // Ubah dari 'url' karena sekarang bisa base64
    ]);

    if ($validation[0] !== 0) return $validation[1];

    try {
        DB::beginTransaction();

        // Update data biasa (tidak terenkripsi)
        $user->gender = $request->gender;

        // Update profile_img jika ada (bisa URL atau base64)
        if ($request->filled('profile_img')) {
            $user->profile_img = $request->profile_img;
        }

        $user->save();

        // Update field terenkripsi dengan encrypt_decrypt_db
        if ($request->filled('name')) {
            try {
                $encryptedName = encrypt_decrypt_db('enc', $request->name, $user->id);
                if ($encryptedName) {
                    User::where('id', $user->id)->update([
                        'name' => DB::raw($encryptedName)
                    ]);
                } else {
                    logger("Name encryption failed for user {$user->id}");
                }
            } catch (\Exception $e) {
                logger("Name encryption error: " . $e->getMessage());
            }
        }

        if ($request->filled('username')) {
            try {
                $encryptedUsername = encrypt_decrypt_db('enc', $request->username, $user->id);
                if ($encryptedUsername) {
                    User::where('id', $user->id)->update([
                        'username' => DB::raw($encryptedUsername)
                    ]);
                } else {
                    logger("Username encryption failed for user {$user->id}");
                }
            } catch (\Exception $e) {
                logger("Username encryption error: " . $e->getMessage());
            }
        }

        if ($request->filled('email')) {
            try {
                $encryptedEmail = encrypt_decrypt_db('enc', $request->email, $user->id);
                if ($encryptedEmail) {
                    User::where('id', $user->id)->update([
                        'email' => DB::raw($encryptedEmail)
                    ]);
                } else {
                    logger("Email encryption failed for user {$user->id}");
                }
            } catch (\Exception $e) {
                logger("Email encryption error: " . $e->getMessage());
            }
        }

        if ($request->filled('nip')) {
            $inputNip = $request->nip;

            // Cek duplikasi NIP
            $duplicate = User::where('id', '!=', $user->id)->get()->some(function ($otherUser) use ($inputNip) {
                try {
                    $decryptedNip = encrypt_decrypt_db('dec', $otherUser->nip, $otherUser->id);
                    return $decryptedNip === $inputNip;
                } catch (\Throwable $e) {
                    return false;
                }
            });

            if ($duplicate) {
                DB::rollBack();
                return json(400, 'false', 'duplicate_nip', 'NIP sudah digunakan oleh pengguna lain.', null);
            }

            // Enkripsi dan update NIP jika tidak ada duplikasi
            try {
                $encryptedNip = encrypt_decrypt_db('enc', $inputNip, $user->id);
                if ($encryptedNip) {
                    User::where('id', $user->id)->update([
                        'nip' => DB::raw($encryptedNip)
                    ]);
                    logger("NIP updated successfully for user {$user->id}");
                } else {
                    logger("NIP encryption failed for user {$user->id}");
                }
            } catch (\Exception $e) {
                logger("NIP encryption error: " . $e->getMessage());
            }
        }

        if ($request->filled('phone_number')) {
            try {
                logger("Original phone_number: " . $request->phone_number);
                $encryptedPhone = encrypt_decrypt_db('enc', $request->phone_number, $user->id);
                logger("Encrypted phone_number: " . ($encryptedPhone ?? 'NULL'));

                if ($encryptedPhone) {
                    User::where('id', $user->id)->update([
                        'phone_number' => DB::raw($encryptedPhone)
                    ]);
                    logger("Phone number updated successfully for user {$user->id}");
                } else {
                    logger("Phone number encryption failed for user {$user->id}");
                }
            } catch (\Exception $e) {
                logger("Phone number encryption error: " . $e->getMessage());
            }
        }

        DB::commit();

        // Ambil ulang data user setelah update
        $updatedUser = User::with(['role', 'department'])->find($user->id);

        // Decrypt semua data yang terenkripsi dengan error handling
        $nameDecrypted = null;
        $usernameDecrypted = null;
        $emailDecrypted = null;
        $nipDecrypted = null;
        $phoneDecrypted = null;

        if ($updatedUser->name) {
            try {
                $nameDecrypted = encrypt_decrypt_db('dec', $updatedUser->name, $updatedUser->id);
            } catch (\Exception $e) {
                logger("Name decryption error: " . $e->getMessage());
            }
        }

        if ($updatedUser->username) {
            try {
                $usernameDecrypted = encrypt_decrypt_db('dec', $updatedUser->username, $updatedUser->id);
            } catch (\Exception $e) {
                logger("Username decryption error: " . $e->getMessage());
            }
        }

        if ($updatedUser->email) {
            try {
                $emailDecrypted = encrypt_decrypt_db('dec', $updatedUser->email, $updatedUser->id);
            } catch (\Exception $e) {
                logger("Email decryption error: " . $e->getMessage());
            }
        }

        if ($updatedUser->nip) {
            try {
                $nipDecrypted = encrypt_decrypt_db('dec', $updatedUser->nip, $updatedUser->id);
                logger("NIP decryption result: " . ($nipDecrypted ?? 'NULL'));
            } catch (\Exception $e) {
                logger("NIP decryption error: " . $e->getMessage());
            }
        }

        if ($updatedUser->phone_number) {
            try {
                logger("Encrypted phone from DB: " . $updatedUser->phone_number);
                $phoneDecrypted = encrypt_decrypt_db('dec', $updatedUser->phone_number, $updatedUser->id);
                logger("Decrypted phone result: " . ($phoneDecrypted ?? 'NULL'));

                if (!$phoneDecrypted) {
                    logger("First decryption failed, trying alternative methods...");
                    $phoneDecrypted = trim($phoneDecrypted);
                    if (empty($phoneDecrypted)) {
                        logger("Phone decryption completely failed, setting to null");
                        $phoneDecrypted = null;
                    }
                }
            } catch (\Exception $e) {
                logger("Phone number decryption error: " . $e->getMessage());
                logger("Stack trace: " . $e->getTraceAsString());
                $phoneDecrypted = null;
            }
        }

        $result = [
            'id' => encrypt_decrypt_md5('enc', $updatedUser->id),
            'name' => $nameDecrypted,
            'username' => $usernameDecrypted,
            'email' => $emailDecrypted,
            'nip' => $nipDecrypted,
            'phone_number' => $phoneDecrypted,
            'gender' => $updatedUser->gender,
            'profile_img' => $updatedUser->profile_img,
            'role_id' => $updatedUser->role?->id,
            'role_name' => $updatedUser->role?->name,
        ];

        return json(200, 'success', 'update_success', 'Profil berhasil diperbarui.', $result);
    } catch (\Exception $e) {
        DB::rollBack();
        logger("Gagal update profil: " . $e->getMessage());
        return json(500, 'false', 'update_failed', 'Terjadi kesalahan saat memperbarui profil.', null);
    }
}

   public function getProfile()
{
    try {
        $authUser = auth()->user();
        $user = User::with('role', 'department')->find($authUser->id);

        // Decrypt data
        $nameDecrypted = null;
        $usernameDecrypted = null;
        $emailDecrypted = null;
        $nipDecrypted = null;
        $phoneDecrypted = null;

        if ($user->name) {
            try { $nameDecrypted = encrypt_decrypt_db('dec', $user->name, $user->id); }
            catch (\Exception $e) { logger("Name decryption error: " . $e->getMessage()); }
        }

        if ($user->username) {
            try { $usernameDecrypted = encrypt_decrypt_db('dec', $user->username, $user->id); }
            catch (\Exception $e) { logger("Username decryption error: " . $e->getMessage()); }
        }

        if ($user->email) {
            try { $emailDecrypted = encrypt_decrypt_db('dec', $user->email, $user->id); }
            catch (\Exception $e) { logger("Email decryption error: " . $e->getMessage()); }
        }

        if ($user->nip) {
            try { $nipDecrypted = encrypt_decrypt_db('dec', $user->nip, $user->id); }
            catch (\Exception $e) { logger("NIP decryption error: " . $e->getMessage()); }
        }

        if ($user->phone_number) {
            try {
                logger("Getting phone raw: " . $user->phone_number);

                $phoneDecrypted = encrypt_decrypt_db('dec', $user->phone_number, $user->id);

                if (empty($phoneDecrypted)) {
                    logger("Phone decryption empty");
                    $phoneDecrypted = null;
                }
            } catch (\Exception $e) {
                logger("Phone decryption error: " . $e->getMessage());
                logger("Stack trace: " . $e->getTraceAsString());
                $phoneDecrypted = null;
            }
        }

        // FOTO PROFIL — sekarang langsung kembalikan base64
        $profileImg = null;

        if (!empty($user->profile_img)) {
            // Jika sudah URL (misal dari DigitalOcean), tetap pakai.
            if (filter_var($user->profile_img, FILTER_VALIDATE_URL)) {
                $profileImg = $user->profile_img;
            } else {
                // Jika base64 → return langsung base64
                $profileImg = $user->profile_img;
            }
        }

        return response()->json([
            'code' => 200,
            'status' => true,
            'title' => 'get_profile_success',
            'message' => 'Data profil berhasil diambil.',
            'data' => [
                'user' => [
                    'id' => encrypt_decrypt_md5('enc', $user->id),
                    'name' => $nameDecrypted,
                    'username' => $usernameDecrypted,
                    'email' => $emailDecrypted,
                    'nip' => $nipDecrypted,
                    'phone_number' => $phoneDecrypted,
                    'gender' => $user->gender,
                    'department_id' => $user->department_id,
                    'department_name' => optional($user->department)->name,
                    'role_id' => $user->role_id,
                    'role_name' => optional($user->role)->name,
                    'status' => $user->status,
                    'profile_img' => $profileImg, // base64 langsung / atau URL DO
                ]
            ]
        ]);

    } catch (\Exception $e) {
        logger("getProfile error: " . $e->getMessage());

        return response()->json([
            'code' => 500,
            'status' => false,
            'title' => 'get_profile_failed',
            'message' => 'Terjadi kesalahan saat mengambil data profil.',
            'data' => null
        ], 500);
    }
}

public function uploadPhoto(Request $request)
{
    try {
        $user = auth()->user();

        if (!$request->hasFile('photo')) {
            return json(400, 'false', 'no_file', 'File foto tidak ditemukan.', null);
        }

        $file = $request->file('photo');

        if (!$file->isValid()) {
            return json(400, 'false', 'invalid_file', 'File tidak valid.', null);
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $maxSize = 2048 * 1024;

        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return json(400, 'false', 'invalid_type', 'Tipe file harus jpeg, png, jpg, atau gif.', null);
        }

        if ($file->getSize() > $maxSize) {
            return json(400, 'false', 'file_too_large', 'Ukuran file maksimal 2MB.', null);
        }

        DB::beginTransaction();

        // CONVERT FILE → BASE64
        $imageData = file_get_contents($file->getRealPath());
        $base64Image = base64_encode($imageData);
        $mimeType = $file->getMimeType();
        $photoData = 'data:' . $mimeType . ';base64,' . $base64Image;

        // SIMPAN KE DATABASE
        User::where('id', $user->id)->update([
            'profile_img' => $photoData
        ]);

        DB::commit();

        $updatedUser = User::find($user->id);
        $encryptedUserId = encrypt_decrypt_md5('enc', $updatedUser->id);

        // RESULT TANPA URL
        $result = [
            'id' => $encryptedUserId,
            'profile_img' => $updatedUser->profile_img,
            'message' => 'Foto profil berhasil diupload.'
        ];

        return json(200, 'success', 'upload_success', 'Foto profil berhasil diupload.', $result);

    } catch (\Exception $e) {
        DB::rollBack();
        return json(500, 'false', 'upload_failed', $e->getMessage(), null);
    }
}

}
