<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\RolePage;

class RoleCrudAccessMiddleware
{
    public function handle(Request $request, Closure $next, $accessType, $pageId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json([
                'code' => 401,
                'status' => false,
                'message' => 'Token tidak valid atau kedaluwarsa',
            ], 401);
        }

        $rolePage = RolePage::where('role_id', $user->role_id)
            ->where('page_id', $pageId)
            ->first();

        $access = $rolePage ? json_decode($rolePage->access, true) : [];

        if (!($access[$accessType] ?? false)) {
            return response()->json([
                'code' => 403,
                'status' => false,
                'message' => 'Akses ' . strtoupper($accessType) . ' ditolak untuk role Anda.',
            ], 403);
        }

        return $next($request);
    }
}
