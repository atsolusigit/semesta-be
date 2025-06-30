<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind User model
        $this->app->bind(User::class, function () {
            return new User();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load custom helper
        require_once base_path('app/Helpers/CustomHelpers.php');

        // Log all executed SQL queries
        // DB::listen(function ($query) {
        //     Log::info("Executed query: " . $query->sql, $query->bindings);
    }
}

