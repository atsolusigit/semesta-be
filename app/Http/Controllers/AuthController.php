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
use App\Http\Middleware\IsSuperAdmin;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\UserToken; //aktifkan jika dibutuhkan
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

    $array_validation = [
        'email' => 'required|string|email:rfc,dns|max:255|unique:users',
        'username' => 'required|string|max:100|unique:users',
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
        // Default value
        $name = $request['username'];
        $profile_img = 'default.png';
        $role_id = 3; // user biasa
        $department_id = 1; //default department
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
            'status' => 0, // pending approval
        ]);

        // Enkripsi email
        User::where('id', $user->id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $user->id))
        ]);

        DB::commit();

        return json(200, 'true', 'success', 'Akun berhasil didaftarkan. Menunggu persetujuan admin.', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
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
    // Validasi input logic
    $array_validation = [
        'username' => 'required|string', // Bisa username atau email terenkripsi
        'password' => 'required',
    ];

    if (check_validation($request->all(), $array_validation)[0] != 0) {
        return check_validation($request->all(), $array_validation)[1];
    }

    try {
        // Ambil user berdasarkan username ATAU email terenkripsi
        $userQuery = User::where('username', $request->username)->first();

        // Jika tidak ditemukan by username, coba cari by email
        if (!$userQuery) {
            // Cari semua user dan dekripsi email satu per satu
            $allUsers = User::all();
            foreach ($allUsers as $user) {
                try {
                    $decryptedEmail = encrypt_decrypt_db('dec', $user->email, $user->id);
                    if ($decryptedEmail && $decryptedEmail === $request->username) {
                        $userQuery = $user;
                        break;
                    }
                } catch (\Exception $e) {
                    // Skip user jika dekripsi gagal
                    continue;
                }
            }
        }

        if (!$userQuery) {
            return json(200, 'false', 'Login Gagal', 'Username/email tidak ditemukan.', []);
        }

        //  Cek status user (pending / ditolak)
        if ($userQuery->status == 0) {
            return json(200, 'false', 'Login Ditolak', 'Akun Anda belum disetujui oleh superadmin.', []);
        }

        if ($userQuery->status == 2) {
            return json(200, 'false', 'Login Ditolak', 'Pendaftaran Akun Anda ditolak.', []);
        }

        //  Autentikasi password
        $token = JWTAuth::attempt([
            'username' => $userQuery->username, // JWTAuth tidak bisa pakai email terenkripsi
            'password' => $request->password
        ]);

        if ($token) {
            //  Ambil user aktif dari token
            $user = JWTAuth::user();

            //  Simpan token ke kolom jtkn
            $user->jtkn = $token;
            $user->save();

            //  Dekripsi email dengan error handling
            $decryptedEmail = null;
            try {
                $decryptedEmail = encrypt_decrypt_db('dec', $user->email, $user->id);

                // Validasi hasil dekripsi
                if (!$decryptedEmail || !filter_var($decryptedEmail, FILTER_VALIDATE_EMAIL)) {
                    logger("Email decryption failed or invalid for user {$user->id}");
                    $decryptedEmail = null;
                }
            } catch (\Exception $e) {
                logger("Email decryption error for user {$user->id}: " . $e->getMessage());
                $decryptedEmail = null;
            }

            // Dekripsi NIP dengan error handling
            $decryptedNip = null;
            try {
                if ($user->nip) {
                    $decryptedNip = encrypt_decrypt_db('dec', $user->nip, $user->id);
                }
            } catch (\Exception $e) {
                logger("NIP decryption error for user {$user->id}: " . $e->getMessage());
                $decryptedNip = null;
            }

            // Dekripsi phone_number dengan error handling
            $decryptedPhone = null;
            try {
                if ($user->phone_number) {
                    $decryptedPhone = encrypt_decrypt_db('dec', $user->phone_number, $user->id);
                }
            } catch (\Exception $e) {
                logger("Phone decryption error for user {$user->id}: " . $e->getMessage());
                $decryptedPhone = null;
            }

            // Load relasi role untuk mendapatkan data role lengkap
            $user->load('role');

            // Siapkan data user untuk response dengan struktur lama
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $decryptedEmail,
                'username' => $user->username,
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
                    'updated_at' => $user->role->updated_at
                ] : null
            ];

            //  Kembalikan token dan data user dengan struktur lama
            return json(200, 'true', 'Login Berhasil', 'Selamat datang!', [
                'user' => $userData,
                'access_token' => [
                    'token' => $token,
                    'type' => 'Bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60,
                ],
                'jtkn' => $user->jtkn,
                'fbtk' => $user->fbtk,
            ]);
        }

        //  Gagal login
        return json(200, 'false', 'Login Gagal', 'Username/ email atau password salah.', []);

    } catch (\Exception $e) {
        logger("Login error: " . $e->getMessage());
        return json(500, 'false', 'Login Error', 'Terjadi kesalahan saat login.', []);
    }
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

        // Validasi pakai helper tetap
        $validate = check_validation($request->all(), $rules);
        if ($validate[0]) {
            return $validate[1]; // Sudah berupa response()->json()
        }

        // Cek password lama
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

        // Simpan password baru
        $user->password = bcrypt($request->new_password);
        $user->save();

        return json(
            200,
            'true',
            'success',
            'Password berhasil diubah.',
            []
        );
    } catch (\Throwable $th) {
        return json(
            500,
            'error',
            'Terjadi kesalahan sistem',
            $th->getMessage(),
            []
        );
    }
}


        public function checkToken(Request $request)
    {
        $token = JWTAuth::getToken();

        $array_validation = ['asdp' => 'required'];

        if (check_validation($request->header(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }

        $asdp = $request->header('asdp');

        if (check_security($asdp, $token) == 0) {
            return json(404, 'false', 'Invalid Token', 'your token is invalid! ' . $asdp . ' - ' . $token, []);
        }

        try {
            $user = auth()->user();
            if (!$user) {
                return json(401, false, 'unauthorized', 'Token tidak valid / kadaluwarsa', []);
            }

            // Load relasi dari ERD
            $user->load(['role.pages', 'departments']);

            return json(200, true, 'success', 'Token masih aktif', [
                'user' => [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'username'     => $user->username,
                    'email'        => encrypt_decrypt_db($user->email, 'decrypt'),
                    'jtkn'         => $user->jtkn,
                    'fbtk'         => $user->fbtk,
                    'role'         => [
                        'id'   => $user->role->id ?? null,
                        'name' => $user->role->name ?? null,
                    ],
                    'pages' => $user->role?->pages->map(function ($page) {
                        return [
                            'id'       => $page->id,
                            'name'     => $page->name,
                            'head_url' => $page->head_url,
                        ];
                    }),
                    'departments' => $user->departments->map(function ($dept) {
                        return [
                            'id'   => $dept->id,
                            'name' => $dept->name,
                        ];
                    }),
                ]
            ]);
        } catch (\Exception $e) {
            return json(500, false, 'server_error', 'Terjadi kesalahan sistem', []);
        }
    }

    public function profile(Request $request)
{
    try {
        // Gunakan JWTAuth agar pasti ambil dari token
        $user = JWTAuth::parseToken()->authenticate();

        if (!$user) {
            return json(401, false, 'unauthorized', 'User tidak ditemukan / token tidak valid', []);
        }

        // Load relasi yang sesuai ERD
        $user->load(['role.pages', 'departments']);

        return json(200, true, 'success', 'Data profil berhasil diambil', [
            'user' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
               'email' => encrypt_decrypt_db('decrypt', $user->email, $user->id),
                'jtkn'     => $user->jtkn,
                'fbtk'     => $user->fbtk,
                'role'     => [
                    'id'   => $user->role->id ?? null,
                    'name' => $user->role->name ?? null,
                ],
                'pages' => $user->role?->pages?->map(function ($page) {
                    return [
                        'id'       => $page->id,
                        'name'     => $page->name,
                        'head_url' => $page->head_url,
                    ];
                }) ?? [],
                'departments' => $user->departments?->map(function ($dept) {
                    return [
                        'id'   => $dept->id,
                        'name' => $dept->name,
                    ];
                }) ?? [],
            ]
        ]);
    }

    // Untuk debugging sementara, bisa aktifkan ini:
        catch (\Exception $e) {
        return json(500, false, 'server_error', 'Gagal mengambil profil user', [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);

        // Kalau sudah production, sebaiknya kembalikan ini saja:
        // return json(500, false, 'server_error', 'Gagal mengambil profil user', []);
    }
}

    }

