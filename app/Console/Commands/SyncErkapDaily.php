<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ErkapSyncService;
use Illuminate\Support\Facades\Log;

class SyncErkapDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erkap:sync-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily upsert Capex Monitor data from ERKAP API';

    /**
     * Execute the console command.
     */
    public function handle(ErkapSyncService $svc)
    {
        $now   = now();
        $tahun = (int) $now->year;
        $bulan = (int) $now->month;
        $week  = (int) ceil($now->day / 7); // week 1-5

        $this->info('🔄 Starting daily ERKAP sync...');
        $this->info("Processing period: Year $tahun | Month $bulan | Week $week");

        try {
            $count = $svc->fetchAndSync($tahun, $bulan, $week);

            $message = "✅ ERKAP Sync completed at " . now() . " — Year: $tahun | Month: $bulan | Week: $week | $count records updated.";

            $this->info($message);

            // Write to dedicated log file
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/erkap-sync.log'),
            ])->info($message);

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $errorMessage = "❌ ERKAP Sync failed at " . now() . " — " . $e->getMessage();

            $this->error($errorMessage);

            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/erkap-sync.log'),
            ])->error($errorMessage, [
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
