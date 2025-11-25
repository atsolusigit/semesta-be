<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\SyncErkapDaily::class,
        \App\Console\Commands\ErkapPrefetchTimeline::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // $syncTime     = (string) config('services.erkap.sync_time', '01:00');
        // $prefetchTime = (string) config('services.erkap.prefetch_time', '22:30');

        $schedule->command('erkap:sync-daily')
            // ->dailyAt($syncTime)
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('erkap:prefetch-timeline')
            // ->dailyAt($prefetchTime)
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();
    }

    protected function scheduleTimezone(): \DateTimeZone|string|null
    {
        return config('app.timezone', 'Asia/Jakarta');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
