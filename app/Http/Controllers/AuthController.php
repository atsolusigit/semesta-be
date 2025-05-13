<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
// use App\Models\UserToken;
// use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
// use Validator;
use Illuminate\Validation\Rules\Password;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(User $user)
    {
        // model as dependency injection
        $this->user = $user;
    }

    public function register(Request $request)
    {
        // validate the incoming request
        // set every field as required
        // set email field so it only accept the valid email format

        //check validation
        $array_validation = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|string|email:rfc,dns|max:255|unique:users',
            'username' => 'required|unique:users',
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'profile_img' => 'required|string|max:255',
        ];

        if (check_validation($request->all(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }
        //check validation

        // if the request valid, create user
        $user = $this->user::create([
            'name' => $request['name'],
            'email' => $request['email'],
            'username' => $request['username'],
            'password' => bcrypt($request['password']),
            'profile_img' => $request['profile_img'],
            
        ]);

        User::where('id', $user->id)->update([
            'email' => DB::raw(encrypt_decrypt_db('enc', $request['email'], $user->id))
        ]);

        // login the user immediately and generate the token
        // $token = auth()->login($user);

        $token = JWTAuth::fromUser($user);

        //return success
        $data = [
            'user' => $user,
            'access_token' => [
                'token' => $token,
                'type' => 'Bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,    // get token expires in seconds
            ],
        ];

        return json(200, 'true', 'success', 'User created successfully!', $data);
        //return success
    }

    public function login(Request $request)
    {
        // $asdp = $request->header('asdp');
        
        //check validation
        // 'email' => 'required|string|email:rfc,dns|max:255',
        $array_validation = [
            'username' => 'required|string',
            'password' => 'required',
        ];

        if (check_validation($request->all(), $array_validation)[0] != 0) {
            return check_validation($request->all(), $array_validation)[1];
        }
        //check validation

        $token = JWTAuth::attempt([
            'username' => $request->username,
            'password' => $request->password
        ]);

        // if token successfully generated then display success response
        // if attempt failed then "unauthenticated" will be returned automatically
        if ($token)
        {
            $user = auth()->user();
            $user['email'] = encrypt_decrypt_db('dec', $user['email'], $user['id']);
            // $menu_rigths = menu_rights(auth()->user()->id);
            
            // UserToken::create([
            //     'userid' => auth()->user()->id,
            //     'token' => $token,
            //     'asdp' => $asdp
            // ]);

            //return success
            $data = [
                'user' => $user,
                // 'menu_rights' => $menu_rigths, 
                'access_token' => [
                    'token' => $token,
                    'type' => 'Bearer',
                    'expires_in' => JWTAuth::factory()->getTTL() * 60,
                ],
            ];

            return json(200, 'true', 'success', 'Login successfully.', $data);
            //return success
        }
        else {
            return json(200, 'false', 'Informasi Login Salah', 'Username atau password anda salah!', []);
        }
    }

    public function logout(Request $request)
    {
        // get token
        $token = JWTAuth::getToken();
        
        // invalidate token
        try {
            $invalidate = JWTAuth::invalidate($token);
        } catch (\Throwable $th) {
            $invalidate = true;
        }

        if($invalidate) {
            return json(200, 'false', 'success', 'Successfully logged out', []);
        }
    }

    // public function changePassword(Request $request) {
    //     // $token = $request->header('Authorization');
        
    //     // get token
    //     $token = JWTAuth::getToken();
        
    //     //check validation
    //     $array_validation = [
    //         'password' => [
    //             'required',
    //             'string',
    //             Password::min(8)
    //             ->mixedCase()
    //             ->letters()
    //             ->numbers()
    //             ->symbols()
    //             ->uncompromised(),
    //         ],
    //     ];
        
    //     if (check_validation($request->all(), $array_validation)[0] != 0) {
    //         return check_validation($request->all(), $array_validation)[1];
    //     }
    //     //check validation
        
    //     $asdp = $request->header('asdp');
    //     //check security
    //     // if (check_security($asdp, $token) == 0) {
    //     //     return json(404, 'false', 'Invalid Token', 'your token is invalid!', []);
    //     // }
    //     //check security

    //     $userid = UserToken::where('asdp', $asdp)->where('token', $token)->first()->userid;

    //     User::where('id', $userid)->update([
    //         'password' => bcrypt($request->password)
    //     ]);

    //     return json(200, 'true', 'success', 'Successfully change password', []);
    // }

    // public function checkToken(Request $request) {
        // get token
        // $token = JWTAuth::getToken();
        
        //check validation
        // $array_validation = [
        //     'asdp' => 'required',
        // ];
        
        // if (check_validation($request->header(), $array_validation)[0] != 0) {
        //     return check_validation($request->all(), $array_validation)[1];
        // }
        //check validation

        // $asdp = $request->header('asdp');

        //check security
        // if (check_security($asdp, $token) == 0) {
        //     return json(404, 'false', 'Invalid Token', 'your token is invalid! '.$asdp.' - '.$token, []);
        // }
        //check security

        // return json(200, 'true', 'success', 'your token still an active', []);
    // }
}
