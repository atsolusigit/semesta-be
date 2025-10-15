<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\JWTException;

class CheckJWT
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
            // if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
            //     return json(401, 'false', 'error', 'Token is Invalid', []);
            // } elseif ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
            //     return json(401, 'false', 'error', 'Token is Expired', []);
            // } else {
            //     return json(401, 'false', 'error', 'Authorization Token not found', []);
            // }
        }

        return $next($request);
    }
}
