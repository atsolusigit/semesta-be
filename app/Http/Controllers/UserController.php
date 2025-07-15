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

        // Update data biasa (tidak terenkripsi)
        $user->name = $request->name;
        $user->username = $request->username;
        $user->gender = $request->gender;

        if ($request->filled('profile_img')) {
            $user->profile_img = $request->profile_img;
        }

        $user->save();

        // Update field terenkripsi dengan error handling
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
            try {
                $encryptedNip = encrypt_decrypt_db('enc', $request->nip, $user->id);
                if ($encryptedNip) {
                    User::where('id', $user->id)->update([
                        'nip' => DB::raw($encryptedNip)
                    ]);
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
        $updatedUser = User::with(['role', 'departments'])->find($user->id);

        // Decrypt semua data yang terenkripsi dengan error handling
        $emailDecrypted = null;
        $nipDecrypted = null;
        $phoneDecrypted = null;

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
            } catch (\Exception $e) {
                logger("NIP decryption error: " . $e->getMessage());
            }
        }

        if ($updatedUser->phone_number) {
            try {
                logger("Encrypted phone from DB: " . $updatedUser->phone_number);
                $phoneDecrypted = encrypt_decrypt_db('dec', $updatedUser->phone_number, $updatedUser->id);
                logger("Decrypted phone result: " . ($phoneDecrypted ?? 'NULL'));

                // Jika dekripsi gagal, coba beberapa alternatif
                if (!$phoneDecrypted) {
                    logger("First decryption failed, trying alternative methods...");

                    // Coba dekripsi dengan cara lain atau fallback
                    // Misalnya jika ada issue dengan format data
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
            'id' => $updatedUser->id,
            'name' => $updatedUser->name,
            'username' => $updatedUser->username,
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
        $user = auth()->user();
        $user = User::with('role', 'department')->find($user->id);

        // Decrypt data dengan error handling
        $emailDecrypted = null;
        $nipDecrypted = null;
        $phoneDecrypted = null;

        if ($user->email) {
            try {
                $emailDecrypted = encrypt_decrypt_db('dec', $user->email, $user->id);
            } catch (\Exception $e) {
                logger("Email decryption error in getProfile: " . $e->getMessage());
            }
        }

        if ($user->nip) {
            try {
                $nipDecrypted = encrypt_decrypt_db('dec', $user->nip, $user->id);
            } catch (\Exception $e) {
                logger("NIP decryption error in getProfile: " . $e->getMessage());
            }
        }

        if ($user->phone_number) {
    try {
        logger("Getting phone from DB: " . $user->phone_number);
        $phoneDecrypted = encrypt_decrypt_db('dec', $user->phone_number, $user->id);

        if (empty($phoneDecrypted)) {
            logger("Phone decryption returned empty result, setting to null");
            $phoneDecrypted = null;
        } else {
            logger("Decrypted phone in getProfile: " . $phoneDecrypted);
        }
    } catch (\Exception $e) {
        logger("Phone number decryption error in getProfile: " . $e->getMessage());
        logger("Stack trace: " . $e->getTraceAsString());
        $phoneDecrypted = null;
    }
}

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
                    'email' => $emailDecrypted,
                    'nip' => $nipDecrypted,
                    'phone_number' => $phoneDecrypted,
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
    } catch (\Exception $e) {
        logger("Error in getProfile: " . $e->getMessage());
        return response()->json([
            'code' => 500,
            'status' => false,
            'title' => 'get_profile_failed',
            'message' => 'Terjadi kesalahan saat mengambil data profil.',
            'data' => null
        ], 500);
    }
}

}
