<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        \App\Console\Commands\SyncErkapDaily::class,
        \App\Console\Commands\ErkapPrefetchTimeline::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily ERKAP monitor sync (current year/month/week)
        $schedule->command('erkap:sync-daily')
            ->dailyAt(config('services.erkap.sync_time', '01:00'))
            ->withoutOverlapping()
            ->onOneServer();

        // Daily prefetch & cache ERKAP capex-timeline (yearly per erkap_id)
        $schedule->command('erkap:prefetch-timeline')
            ->dailyAt('22:30')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Get the timezone that should be used by default for scheduled events.
     */
    protected function scheduleTimezone(): \DateTimeZone|string|null
    {
        return config('app.timezone', 'Asia/Jakarta');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
