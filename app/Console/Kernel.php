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
<<<<<<< HEAD
        $syncTime     = (string) config('services.erkap.sync_time', '01:00');
        $prefetchTime = (string) config('services.erkap.prefetch_time', '22:30');

        $schedule->command('erkap:sync-daily')
            ->dailyAt($syncTime)
=======
        // $syncTime     = (string) config('services.erkap.sync_time', '01:00');
        // $prefetchTime = (string) config('services.erkap.prefetch_time', '22:30');

        $schedule->command('erkap:sync-daily')
            // ->dailyAt($syncTime)
            ->hourly()
>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('erkap:prefetch-timeline')
<<<<<<< HEAD
            ->dailyAt($prefetchTime)
            ->withoutOverlapping()
            ->onOneServer();
=======
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        $schedule->command('erkap:sync-risk')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

>>>>>>> c25d44c91562d73f06dbf7a5ec1f721825bdbfae
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
