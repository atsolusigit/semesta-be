protected function schedule(Schedule $schedule)
{
    $schedule
        ->command('erkap:sync-daily')
        ->dailyAt(config('services.erkap.sync_time', '01:00'))
        ->withoutOverlapping()
        ->onOneServer();
}
