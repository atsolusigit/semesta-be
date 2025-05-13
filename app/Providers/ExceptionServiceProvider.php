<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\ExceptionRenderer;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Auth\AuthenticationException;

class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->resolving(ExceptionRenderer::class, function (ExceptionRenderer $renderer) {
            $renderer->renderable(function (TokenInvalidException $e, $request) {
                return response()->json(['error' => 'Token is invalid'], 401);
            });

            $renderer->renderable(function (TokenExpiredException $e, $request) {
                return response()->json(['error' => 'Token has expired'], 401);
            });

            $renderer->renderable(function (JWTException $e, $request) {
                return response()->json(['error' => 'Token could not be parsed'], 401);
            });

            $renderer->renderable(function (AuthenticationException $e, $request) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            });
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
