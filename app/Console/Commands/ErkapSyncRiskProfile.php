<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ErkapRiskProfileService;

class ErkapSyncRiskProfile extends Command
{
    protected $signature = 'erkap:sync-risk {year?}';
    protected $description = 'Sync ERKAP Capex Risk Profile for a given year (default: current year)';

    public function handle(ErkapRiskProfileService $svc)
    {
        $year = (int)($this->argument('year') ?: now()->year);
        $this->info("🔄 Syncing ERKAP risk profile for year {$year}...");
        $count = $svc->fetchAndSync($year);
        $this->info("✅ Done. {$count} records upserted.");
        return self::SUCCESS;
    }
}
