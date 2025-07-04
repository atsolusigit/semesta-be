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


class UserController extends Controller
{
   public function index()
{
    $users = User::with(['role:id,name', 'departments:id,name'])
    ->select('id','name','username','email','role_id','status')
    ->latest('id')
    ->get();


    foreach ($users as $user) {
        try {
            $decryptedEmail = encrypt_decrypt_db('dec', $user->email, $user->id);

            // Pastikan hasil dekripsi valid UTF-8
            if (!mb_check_encoding($decryptedEmail, 'UTF-8')) {
                \Log::warning("Email hasil dekripsi bukan UTF-8 valid untuk user ID {$user->id}");
                $decryptedEmail = null;
            }

            $user->email = $decryptedEmail;
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt email #{$user->id}: {$e->getMessage()}");
            $user->email = null;
        }

        $user->role_name = $user->role->name ?? '-';
        $user->department_name = $user->departments->pluck('name');
    }

    return json(200, 'success', 'Success','Berhasil menampilkan semua data user', $users);
}


   public function show(Request $request, $id)
{
    $check = check_validation(['id' => $id], [
        'id' => 'required|numeric|exists:users,id'
    ]);
    if ($check[0] === 1) return $check[1];

    $user = User::with(['role', 'departments:id,name'])
    ->select('id', 'name', 'username', 'email', 'role_id', 'status')
    ->find($id);


    if (!$user) {
        return json(404, 'error', 'Not Found', 'User tidak ditemukan', null);
    }

    // Decrypt email
    try {
        $decryptedEmail = encrypt_decrypt_db('dec', $user->email, $user->id);

        // Validasi UTF-8
        if (!mb_check_encoding($decryptedEmail, 'UTF-8')) {
            \Log::warning("Email hasil dekripsi bukan UTF-8 valid untuk user ID {$user->id}");
            $decryptedEmail = null;
        }

        $user->email = $decryptedEmail;
    } catch (\Throwable $e) {
        \Log::warning("Gagal decrypt email user ID {$user->id}: {$e->getMessage()}");
        $user->email = null;
    }

    $user->role_name = optional($user->role)->name ?? '-';
    $user->department_name = $user->departments->pluck('name');

    // Jangan lupa return JSON-nya
    return json(200, 'success', 'Success', 'Berhasil menampilkan detail user', $user);
}


 public function store(Request $request)
{
    DB::beginTransaction();

    $array_validation = [
        'name' => 'required|string|min:2|max:255',
        'email' => 'required|string|email:rfc,dns|max:255|unique:users',
        'username' => 'required|string|max:100|unique:users',
        'password' => [
            'required',
            'string',
            Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
        ],
        'profile_img' => 'nullable|string|max:255', // hanya link
        'role_id' => 'required|integer|exists:mst_role,id',
        'jtkn' => 'nullable|string|max:500',
        'fbtk' => 'nullable|string|max:500',
        'department_id' => 'required|integer',
        'status' => 'required',
        'access_pages' => 'array',
        'access_departments' => 'array',
    ];

    $validation = check_validation($request->all(), $array_validation);
    if ($validation[0] != 0) {
        return $validation[1];
    }

    try {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'profile_img' => $request->profile_img ?? '',
            'role_id' => $request->role_id,
            'jtkn' => '',
            'fbtk' => $request->fbtk ?? 'FBTK-' . strtoupper(Str::random(10)),
            'department_id' => $request->department_id,
            'status' => $request->status === 'aktif' || $request->status == 1 ? 1 : 0,
        ]);

        User::where('id', $user->id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request->email, $user->id))
        ]);

        $token = JWTAuth::fromUser($user);
        $user->jtkn = $token;
        $user->save();

        // Simpan akses halaman
        if (!empty($request->access_pages)) {
            $pivotPages = [];
            foreach ($request->access_pages as $pageId) {
                $pivotPages[] = [
                    'user_id' => $user->id,
                    'mst_page_id' => $pageId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('user_page')->insert($pivotPages);
        }

        // Simpan akses department
        if (!empty($request->access_departments)) {
            $pivotDepartments = [];
            foreach ($request->access_departments as $deptId) {
                $pivotDepartments[] = [
                    'user_id' => $user->id,
                    'department_id' => $deptId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('tr_user_department')->insert($pivotDepartments);
        }

        DB::commit();

        return json(200, 'true', 'success', 'User berhasil ditambahkan!', []);
    } catch (\Exception $e) {
        DB::rollback();
        logger("Gagal tambah user: " . $e->getMessage());
        return json(500, 'false', 'error', 'Gagal tambah user!', []);
    }
}


public function update(Request $request, $id)
{
    DB::beginTransaction();

    $array_validation = [
        'id' => 'required|exists:users,id',
        'name' => 'required|string|min:2|max:255',
        'email' => 'required|string|email:rfc,dns|max:255|unique:users,email,' . $id,
        'username' => 'required|string|max:100|unique:users,username,' . $id,
        'password' => [
            'nullable',
            'string',
            Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
        ],
        'profile_img' => 'nullable|string|max:255', // hanya link
        'role_id' => 'required|integer|exists:mst_role,id',
        'jtkn' => 'nullable|string|max:500',
        'fbtk' => 'nullable|string|max:500',
        'department_id' => 'required|integer',
        'status' => 'required',
        'access_pages' => 'array',
        'access_departments' => 'array',
    ];

    $validation = check_validation($request->all() + ['id' => $id], $array_validation);
    if ($validation[0] != 0) {
        return $validation[1];
    }

    try {
        $user = User::findOrFail($id);

        $user->update([
            'name' => $request->name,
            'username' => $request->username,
            'password' => $request->filled('password') ? bcrypt($request->password) : $user->password,
            'profile_img' => $request->profile_img ?? '',
            'role_id' => $request->role_id,
            'fbtk' => $request->fbtk ?? $user->fbtk,
            'department_id' => $request->department_id,
            'status' => $request->status === 'aktif' || $request->status == 1 ? 1 : 0,
        ]);

        User::where('id', $id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request->email, $id))
        ]);

        DB::table('user_page')->where('user_id', $id)->delete();
        if (!empty($request->access_pages)) {
            $pivotPages = [];
            foreach ($request->access_pages as $pageId) {
                $pivotPages[] = [
                    'user_id' => $id,
                    'mst_page_id' => $pageId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('user_page')->insert($pivotPages);
        }

        DB::table('tr_user_department')->where('user_id', $id)->delete();
        if (!empty($request->access_departments)) {
            $pivotDepartments = [];
            foreach ($request->access_departments as $deptId) {
                $pivotDepartments[] = [
                    'user_id' => $id,
                    'department_id' => $deptId,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('tr_user_department')->insert($pivotDepartments);
        }

        DB::commit();

        return json(200, 'true', 'success', 'User berhasil diperbarui!', []);
    } catch (\Exception $e) {
        DB::rollback();
        logger("Gagal update user: " . $e->getMessage());
        return json(500, 'false', 'error', 'Gagal update user!', []);
    }
}

    public function destroy(Request $request, $id)
    {
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

    public function dropdownData()
{
    $roles = \App\Models\MstRole::select('id', 'name')->get();
    $departments = \App\Models\MstDepartment::select('id', 'name')->get();

    return json(200, 'success', 'Success', 'Dropdown data berhasil dimuat', [
        'roles' => $roles,
        'departments' => $departments,
    ]);
}


    // Approve user
public function approveUser($id)
{
    $user = User::find($id);
    if (!$user) {
        return json(404, 'false', 'not_found', 'User tidak ditemukan.', []);
    }

    $user->status = 1; // aktifkan
    $user->save();

    return json(200, 'true', 'success', 'User berhasil diaktifkan.', []);
}

// Reject user
public function rejectUser($id)
{
    $user = User::find($id);
    if (!$user) {
        return json(404, 'false', 'not_found', 'User tidak ditemukan.', []);
    }

    $user->status = 2; // tolak
    $user->save();

    return json(200, 'true', 'success', 'User berhasil ditolak.', []);
}

public function getPendingUsers()
{
    $users = User::with('role:id,name')
        ->select('id','name','username','email','role_id','status')
        ->where('status', 0) // hanya user pending
        ->latest('id')
        ->get();

    foreach ($users as $user) {
        try {
            $decryptedEmail = encrypt_decrypt_db('dec', $user->email, $user->id);

            if (!mb_check_encoding($decryptedEmail, 'UTF-8')) {
                \Log::warning("Email hasil dekripsi bukan UTF-8 valid untuk user ID {$user->id}");
                $decryptedEmail = null;
            }

            $user->email = $decryptedEmail;
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt email #{$user->id}: {$e->getMessage()}");
            $user->email = null;
        }

        $user->role_name = $user->role->name ?? '-';
    }

    return json(200, 'success', 'Success', 'Berhasil menampilkan user dengan status pending', $users);
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
        'department_id' => 'required|exists:mst_department,id',
        'profile_img' => 'nullable|url',
    ]);

    if ($validation[0] !== 0) return $validation;

    try {
        DB::beginTransaction();

        // Update data user
        $user->name = $request->name;
        $user->username = $request->username;
        $user->nip = $request->nip;
        $user->phone_number = $request->phone_number;
        $user->department_id = $request->department_id;

        if ($request->filled('profile_img')) {
            $user->profile_img = $request->profile_img;
        }

        $user->save();

        // Update email terenkripsi jika diisi
        if ($request->filled('email')) {
            User::where('id', $user->id)->update([
                'email' => DB::raw(encrypt_decrypt_db('enc', $request->email, $user->id))
            ]);
        }

        DB::commit();

        // Ambil ulang data user setelah update
        $updatedUser = User::with(['role', 'departments'])->find($user->id);
        $emailDecrypted = encrypt_decrypt_db('dec', $updatedUser->email, $updatedUser->id);
        $department = $updatedUser->departments->first();

        $result = [
            'id' => $updatedUser->id,
            'name' => $updatedUser->name,
            'username' => $updatedUser->username,
            'email' => $emailDecrypted,
            'nip' => $updatedUser->nip,
            'phone_number' => $updatedUser->phone_number,
            'profile_img' => $updatedUser->profile_img,
            'department_id' => $department?->id,
            'department_name' => $department?->name,
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
    $user = auth()->user();
    $user = User::with('role')->find($user->id);

    return response()->json([
        'code' => 200,
        'status' => true,
        'title' => 'get_profile_success',
        'message' => 'Data profil berhasil diambil.',
        'data' => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => encrypt_decrypt_db('dec', $user->email, $user->id),
                'nip' => $user->nip,
                'phone_number' => $user->phone_number,
                'department_id' => $user->department_id,
                'role_id' => $user->role_id,
                'role_name' => $user->role->name ?? null,
                'status' => $user->status,
                'profile_img' => $user->profile_img,
            ]
        ]
    ]);
}


}
