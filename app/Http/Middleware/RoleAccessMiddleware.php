<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class RoleAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  mixed  ...$allowedRoles (seperti: 1,2,3)
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next, ...$allowedRoles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 401,
                'status'  => false,
                'title'   => 'unauthorized',
                'message' => 'Token tidak valid atau kedaluwarsa',
                'data'    => []
            ], 401);
        }

        if (!$user || !in_array((string)$user->role_id, array_map('strval', $allowedRoles))) {
            return response()->json([
                'code'    => 403,
                'status'  => false,
                'title'   => 'forbidden',
                'message' => 'Anda tidak memiliki akses untuk fitur ini',
                'data'    => []
            ], 403);
        }

        return $next($request);
    }
}
