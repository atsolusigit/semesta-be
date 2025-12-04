<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use App\Http\Middleware\CheckJWT;
use App\Http\Middleware\Authenticate;
use Illuminate\Auth\AuthenticationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'jwt'  => CheckJWT::class,
            'auth' => Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 401);
            }
        });
    })
    ->withSchedule(function (Schedule $schedule) {
        $tz = (string) config('app.timezone', 'Asia/Jakarta');

        $schedule->command('erkap:sync-daily')
            ->hourly()
            ->timezone($tz)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('erkap:prefetch-timeline')
            ->hourly()
            ->timezone($tz)
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('erkap:sync-risk')
            ->hourly()
            ->timezone($tz)
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->create();
