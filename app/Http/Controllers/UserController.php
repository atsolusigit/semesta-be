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
        $users = User::with('role:id,name')
            ->select('id','name','username','email','role_id','status')
            ->latest('id')
            ->get();

        foreach ($users as $user) {
            try {
                $user->email = encrypt_decrypt_db('dec', $user->email, $user->id);
            } catch (\Throwable $e) {
                \Log::warning("Gagal decrypt email #{$user->id}: {$e->getMessage()}");
            }

            $user->role_name = $user->role->name ?? '-';
        }

        return json(200, 'success', 'Success','Berhasil menampilkan semua data user', $users);
    }

    public function show(Request $request, $id)
    {
        $check = check_validation(['id' => $id], [
            'id' => 'required|numeric|exists:users,id'
        ]);
        if ($check[0] === 1) return $check[1];

        $user = User::with('role')
            ->select('id', 'name', 'username', 'email', 'role_id', 'status')
            ->find($id);

        if (!$user) {
            return json(404, 'error', 'Not Found', 'User tidak ditemukan', null);
        }

        try {
            $user->email = encrypt_decrypt_db('dec', $user->email, $user->id);
        } catch (\Throwable $e) {
            \Log::warning("Gagal decrypt email user ID {$user->id}: {$e->getMessage()}");
        }

        $user->role_name = optional($user->role)->name ?? '-';

        return json(200, 'success', 'Success', 'Berhasil menampilkan detail user', $user);
    }

 public function store(Request $request)
{
    DB::beginTransaction();

    $array_validation = [
        'name' => 'required|string|min:2|max:255',
        'email' => 'required|string|email:rfc,dns|max:255|unique:users',
        'username' => 'required|unique:users',
        'password' => [
            'required',
            'string',
            Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
        ],
        'profile_img' => 'nullable|string|max:255',
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
            'name' => $request['name'],
            'email' => $request['email'], // sementara plain dulu
            'username' => $request['username'],
            'password' => bcrypt($request['password']),
            'profile_img' => $request['profile_img'] ?? '',
            'role_id' => $request['role_id'],
            'jtkn' => '',
            'fbtk' => $request->fbtk ?? 'FBTK-' . strtoupper(Str::random(10)),
            'department_id' => $request['department_id'],
            'status' => $request['status'] === 'aktif' || $request['status'] == 1 ? 1 : 0,
        ]);

        // Update email setelah user.id tersedia
        User::where('id', $user->id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $user->id))
        ]);

        // Buat JWT token & simpan ke kolom jtkn
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
        'username' => 'required|unique:users,username,' . $id,
        'password' => [
            'nullable',
            'string',
            Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
        ],
        'profile_img' => 'nullable|string|max:255',
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
            'name' => $request['name'],
            'username' => $request['username'],
            'password' => $request->filled('password') ? bcrypt($request['password']) : $user->password,
            'profile_img' => $request['profile_img'] ?? '',
            'role_id' => $request['role_id'],
            'fbtk' => $request->fbtk ?? $user->fbtk,
            'department_id' => $request['department_id'],
            'status' => $request['status'] === 'aktif' || $request['status'] == 1 ? 1 : 0,
        ]);

        // Update email terenkripsi
        User::where('id', $id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $id))
        ]);

        // Hapus & Insert ulang akses pages
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

        // Hapus & Insert ulang akses departments
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
        $roles = MstRole::select('id', 'name')->get();
        $departments = \App\Models\MstDepartment::select('id', 'name')->get();

        return json(200, 'success', 'success', 'Dropdown data berhasil dimuat', [
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

public function getPendingUsers(Request $request)
{
    try {
        $pendingUsers = User::where('status', 0)->with('role')->get();

        // Convert semua data string agar aman untuk JSON (UTF-8 valid)
        $usersCleaned = $pendingUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => mb_convert_encoding($user->name, 'UTF-8', 'UTF-8'),
                'username' => mb_convert_encoding($user->username, 'UTF-8', 'UTF-8'),
                'email' => mb_convert_encoding($user->email, 'UTF-8', 'UTF-8'),
                'profile_img' => $user->profile_img,
                'role_id' => $user->role_id,
                'status' => $user->status,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => mb_convert_encoding($user->role->name, 'UTF-8', 'UTF-8')
                ] : null
            ];
        });

        return json(200, 'true', 'success', 'Daftar user yang menunggu persetujuan.', [
            'users' => $usersCleaned
        ]);
    } catch (\Exception $e) {
        return json(500, 'false', 'server_error', $e->getMessage(), []);
    }
}

}
