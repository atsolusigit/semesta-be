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

class UserController extends Controller
{

  public function index()
{
    $users = User::with(['role:id,name', 'department:id,name'])
        ->select('id', 'name', 'username', 'email', 'role_id', 'status', 'department_id')
        ->orderBy('id', 'asc')
        ->get();

    $result = [];

    foreach ($users as $user) {
        $result[] = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => encrypt_decrypt_db('dec', $user->email, $user->id),
            'role_id' => $user->role_id,
            'department_id' => $user->department_id,
            'status' => $user->status,
            'role_name' => $user->role?->name,
            'department_name' => $user->department?->name,
        ];
    }

    return response()->json([
        'status' => true,
        'message' => 'List data user.',
        'data' => $result
    ]);
}


public function show(Request $request, $id)
{
    // Validasi ID
    $check = check_validation(['id' => $id], [
        'id' => 'required|numeric|exists:users,id'
    ]);
    if ($check[0] === 1) return $check[1];

    // Ambil data user dengan relasi
    $user = User::with(['role:id,name', 'department:id,name'])
        ->select('id', 'name', 'username', 'email', 'role_id', 'status', 'department_id')
        ->find($id);

    if (!$user) {
        return json(404, 'error', 'Not Found', 'User tidak ditemukan', null);
    }

    // Dekripsi email
    try {
        $user->email = encrypt_decrypt_db('dec', $user->email, $user->id);
    } catch (\Throwable $e) {
        \Log::warning("Gagal decrypt email user ID {$user->id}: {$e->getMessage()}");
        $user->email = null;
    }

    // Susun response
    $data = [
        'id' => $user->id,
        'name' => $user->name,
        'username' => $user->username,
        'email' => $user->email,
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
        'profile_img' => 'required|string|url',
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
            'email' => $request->email, // akan dienkripsi setelahnya
            'password' => bcrypt($request->password),
            'role_id' => $request->role_id,
            'department_id' => $request->department_id,
            'status' => $status,
            'profile_img' => $request->profile_img,
            'jtkn' => '',
            'fbtk' => $fbtk,
        ]);

        // Enkripsi email sesuai user ID
        User::where('id', $user->id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $user->id))
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
    $user = User::find($id);
    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User tidak ditemukan.'
        ], 404);
    }

    $array_validation = [
        'name' => 'required|string|max:255',
        'username' => 'required|string|max:100|unique:users,username,' . $id,
        'email' => 'required|email|unique:users,email,' . $id,
        'role_id' => 'required|exists:mst_role,id',
        'department_id' => 'required|exists:mst_department,id',
        'status' => ['required', Rule::in(['aktif', 'pasif'])],
        'profile_img' => 'required|string|url',
    ];

    $validation = check_validation($request->all(), $array_validation);
    if ($validation[0] != 0) {
        return $validation[1];
    }

    try {
        $status = $request->status === 'aktif' ? 1 : 0;

        $user->name = $request->name;
        $user->username = $request->username;
        $user->role_id = $request->role_id;
        $user->department_id = $request->department_id;
        $user->status = $status;
        $user->profile_img = $request->profile_img;

        // Enkripsi email berdasarkan ID user
         // Enkripsi email sesuai user ID
        User::where('id', $user->id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $user->id))
        ]);

        // Update password jika dikirim
        if (!empty($request->password)) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'User berhasil diperbarui.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Terjadi kesalahan saat memperbarui user.',
            'error' => $e->getMessage()
        ], 500);
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
        'gender' => 'nullable|in:male,female,other',
        'profile_img' => 'nullable|url',
    ]);

    if ($validation[0] !== 0) return $validation;

    try {
        DB::beginTransaction();

        $user->name = $request->name;
        $user->username = $request->username;
        $user->nip = $request->nip;
        $user->phone_number = $request->phone_number;
        $user->gender = $request->gender;

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

        $result = [
            'id' => $updatedUser->id,
            'name' => $updatedUser->name,
            'username' => $updatedUser->username,
            'email' => $emailDecrypted,
            'nip' => $updatedUser->nip,
            'phone_number' => $updatedUser->phone_number,
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
    $user = auth()->user();
    $user = User::with('role', 'department')->find($user->id); // relasi yang dipakai adalah singular

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
                'gender' => $user->gender,
                'department_id' => $user->department_id,
                'department_name' => optional($user->department)->name,
                'role_id' => $user->role_id,
                'role_name' => optional($user->role)->name,
                'status' => $user->status,
                'profile_img' => $user->profile_img,
            ]
        ]
    ]);
}

}
