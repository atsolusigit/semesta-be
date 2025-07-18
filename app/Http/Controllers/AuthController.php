<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\MstPage;
use App\Models\MstDepartment;
use App\Models\MstDivision;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\UserToken;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function register(Request $request)
    {
        DB::beginTransaction();

        $existingUsernames = User::all()->map(function ($user) {
            try {
                return encrypt_decrypt_db('dec', $user->username, $user->id);
            } catch (\Throwable $e) {
                return null;
            }
        })->filter();

        if ($existingUsernames->contains($request->username)) {
            return response()->json([
                'code' => 400,
                'status' => 'error_validation',
                'message' => 'error validation. [400 - bad request]',
                'data' => [
                    'username' => ['The username has already been taken.']
                ]
            ], 400);
        }


        $array_validation = [
            'email' => 'required|string|email:rfc,dns|max:255|unique:users',
            'username' => 'required|string|max:100',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised(),
            ],
        ];

        $validation = check_validation($request->all(), $array_validation);
        if ($validation[0] != 0) {
            return $validation[1];
        }

        try {
            $name = $request['username'];
            $profile_img = 'default.png';
            $role_id = 3;
            $department_id = 1;
            $fbtk = 'FBTK-' . strtoupper(Str::random(10));

            $user = $this->user::create([
                'name' => $name,
                'email' => $request['email'],
                'username' => $request['username'],
                'password' => bcrypt($request['password']),
                'profile_img' => $profile_img,
                'role_id' => $role_id,
                'jtkn' => '',
                'fbtk' => $fbtk,
                'department_id' => $department_id,
                'status' => 0,
            ]);

            User::where('id', $user->id)->update([
                'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $user->id)),
                'name' => DB::raw(encrypt_decrypt_db('enc', $name, $user->id)),
                'username' => DB::raw(encrypt_decrypt_db('enc', $request['username'], $user->id)),
            ]);

            DB::commit();

            return json(200, 'true', 'success', 'Akun berhasil didaftarkan. Menunggu persetujuan admin.', [
                'user' => [
                    'id' => encrypt_decrypt_md5('enc', $user->id),
                    'name' => $name,
                    'username' => $request->username,
                    'email' => $request->email,
                    'role_id' => $user->role_id,
                    'role_name' => optional($user->role)->name,
                    'status' => $user->status,
                    'profile_img' => $user->profile_img,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return json(500, 'false', 'register_failed', $e->getMessage(), []);
        }
    }

    public function login(Request $request)
{
    $array_validation = [
        'username' => 'required|string',
        'password' => 'required',
    ];

        if (check_validation($request->all(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }
      
    try {
        // Ambil user ID berdasarkan pencocokan username/email terenkripsi langsung dari database
        $input = $request->username;

        $user = DB::table('users')
            ->whereRaw("AES_DECRYPT(username, CONCAT('SM', id)) = ?", [$input])
            ->orWhereRaw("AES_DECRYPT(email, CONCAT('SM', id)) = ?", [$input])
            ->first();

        if (!$user) {
            return json(200, 'false', 'Login Gagal', 'Username/email tidak ditemukan.', []);
        }

        $user = User::find($user->id); // Ambil model aslinya agar bisa pakai relasi dll

        // Status user
        if ($user->status == 0) {
            return json(200, 'false', 'Login Ditolak', 'Akun Anda belum disetujui oleh superadmin.', []);
        }

        if ($user->status == 2) {
            return json(200, 'false', 'Login Ditolak', 'Pendaftaran Akun Anda ditolak.', []);
        }

        // Cek password
        if (!Hash::check($request->password, $user->password)) {
            return json(200, 'false', 'Login Gagal', 'Username/email atau password salah.', []);
        }

        // Generate token
        $token = JWTAuth::fromUser($user);
        $user->jtkn = $token;
        $user->save();

        // Dekripsi data user
        $decryptedName = encrypt_decrypt_db('dec', $user->name, $user->id);
        $decryptedUsername = encrypt_decrypt_db('dec', $user->username, $user->id);
        $decryptedEmail = encrypt_decrypt_db('dec', $user->email, $user->id);
        $decryptedNip = $user->nip ? encrypt_decrypt_db('dec', $user->nip, $user->id) : null;
        $decryptedPhone = $user->phone_number ? encrypt_decrypt_db('dec', $user->phone_number, $user->id) : null;

        $user->load('role');

        return json(200, 'true', 'Login Berhasil', 'Selamat datang!', [
            'user' => [
                'id' => encrypt_decrypt_md5('enc', $user->id),
                'name' => $decryptedName,
                'username' => $decryptedUsername,
                'email' => $decryptedEmail,
                'email_verified_at' => $user->email_verified_at,
                'status' => $user->status,
                'profile_img' => $user->profile_img ?? "",
                'department_id' => $user->department_id,
                'jtkn' => $user->jtkn,
                'fbtk' => $user->fbtk,
                'role_id' => $user->role_id,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'nip' => $decryptedNip,
                'phone_number' => $decryptedPhone,
                'gender' => $user->gender,
                'photo' => $user->photo,
                'role_name' => optional($user->role)->name,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'status' => $user->role->status,
                    'created_by' => $user->role->created_by,
                    'created_at' => $user->role->created_at,
                    'updated_at' => $user->role->updated_at,
                ] : null,
            ],
            'access_token' => [
                'token' => $token,
                'type' => 'Bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
            ],
            'jtkn' => $user->jtkn,
            'fbtk' => $user->fbtk,
        ]);

    } catch (\Exception $e) {
        Log::error("Login error: " . $e->getMessage());
        return json(500, 'false', 'Login Error', 'Terjadi kesalahan saat login.', []);
    }

    public function logout(Request $request)
    {
        $token = JWTAuth::getToken();

        try {
            $invalidate = JWTAuth::invalidate($token);
        } catch (\Throwable $th) {
            $invalidate = true;
        }

        if ($invalidate) {
            return json(200, 'true', 'success', 'Berhasil logged out', []);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            $rules = [
                'old_password' => 'required',
                'new_password' => 'required|min:6',
                'confirm_password' => 'required|same:new_password',
            ];

            $validate = check_validation($request->all(), $rules);
            if ($validate[0]) {
                return $validate[1];
            }

            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json([
                    'code' => 400,
                    'status' => 'error_validation',
                    'message' => 'error validation. [400 - bad request]',
                    'data' => [
                        'old_password' => ['Password lama salah.']
                    ]
                ], 200);
            }

            $user->password = bcrypt($request->new_password);
            $user->save();

            return json(200, 'true', 'success', 'Password berhasil diubah.', []);
        } catch (\Throwable $th) {
            return json(500, 'error', 'Terjadi kesalahan sistem', $th->getMessage(), []);
        }
    }

   public function checkToken(Request $request)
{
    try {
        // Cek apakah token ada di Bearer header
        $token = JWTAuth::getToken();
        if (!$token) {
            return json(401, false, 'token_absent', 'Token tidak ditemukan di Bearer header', []);
        }

        // Validasi header asdp secara langsung
        if (!$request->header('asdp')) {
            return json(200, true, 'success_validation', 'Token sudah terisi dengan benar dan masih aktif', []);
        }
    }
     
        // Ambil header asdp
        $asdp = $request->header('asdp');

        // Validasi security token
        if (check_security($asdp, $token) == 0) {
            return json(401, false, 'invalid_token', 'Token tidak valid', [
                'message' => 'Token yang diberikan tidak valid atau telah kadaluwarsa'
            ]);
        }

        // Validasi user dari token
        $user = auth()->user();
        if (!$user) {
            return json(401, false, 'unauthorized', 'Token tidak valid atau kadaluwarsa', []);
        }

        // Load relasi yang diperlukan
        $user->load(['role.pages', 'departments']);

        // Return response sukses dengan data user
        return json(200, true, 'success', 'Token sudah terisi dengan benar dan masih aktif', [
          
            'user' => [
                'id' => encrypt_decrypt_md5('enc', $user->id),
                'name' => encrypt_decrypt_db('dec', $user->name, $user->id),
                'username' => encrypt_decrypt_db('dec', $user->username, $user->id),
                'email' => encrypt_decrypt_db('dec', $user->email, $user->id),
                'jtkn' => $user->jtkn,
                'fbtk' => $user->fbtk,
                'role' => [
                    'id' => $user->role->id ?? null,
                    'name' => $user->role->name ?? null,
                ],
                'pages' => $user->role?->pages->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'name' => $page->name,
                        'head_url' => $page->head_url,
                    ];
                }) ?? [],
                'departments' => $user->departments->map(function ($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                    ];
                }) ?? [],
            ],
            'token_info' => [
                'status' => 'valid',
                'checked_at' => now()->toDateTimeString()
            ]
        ]);

    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        return json(401, false, 'token_expired', 'Token telah kadaluwarsa', []);
    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return json(401, false, 'token_invalid', 'Token tidak valid', []);
    } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        return json(401, false, 'token_absent', 'Token tidak ditemukan', []);
    } catch (\Exception $e) {
        return json(500, false, 'server_error', 'Terjadi kesalahan sistem', [
            'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
        ]);
    }
}


   public function profile(Request $request)
{
    try {
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user) {
            return json(401, false, 'unauthorized', 'User tidak ditemukan / token tidak valid', []);
        }

        $user->load(['role.pages', 'departments']);

        return json(200, true, 'success', 'Data profil berhasil diambil', [
            'user' => [
                'id' => encrypt_decrypt_md5('enc', $user->id),
                'name' => encrypt_decrypt_db('dec', $user->name, $user->id),
                'username' => encrypt_decrypt_db('dec', $user->username, $user->id),
                'email' => encrypt_decrypt_db('dec', $user->email, $user->id),
                'jtkn' => $user->jtkn,
                'fbtk' => $user->fbtk,
                'role' => [
                    'id' => $user->role->id ?? null,
                    'name' => $user->role->name ?? null,
                ],
                'pages' => $user->role?->pages?->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'name' => $page->name,
                        'head_url' => $page->head_url,
                    ];
                }) ?? [],
                'departments' => $user->departments?->map(function ($dept) {
                    return [
                        'id' => $dept->id,
                        'name' => $dept->name,
                    ];
                }) ?? [],
            ]
        ]);
    } catch (\Exception $e) {
        return json(500, false, 'server_error', 'Gagal mengambil profil user', [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
    }
}

}
