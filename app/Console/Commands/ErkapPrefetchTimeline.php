<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RencanaInvestasi;
use App\Jobs\SyncErkapTimelineYear;

class ErkapPrefetchTimeline extends Command
{
    protected $signature = 'erkap:prefetch-timeline {year?}';
    protected $description = 'Prefetch & cache capex-timeline per (erkap_id, year)';

    public function handle()
    {
        $year = (int)($this->argument('year') ?: now()->year);

        $erkapIds = RencanaInvestasi::query()
            ->where('year', $year)
            ->whereNotNull('erkap_id')
            ->distinct()
            ->pluck('erkap_id')
            ->filter()              // buang null/0
            ->unique()
            ->values();

        if ($erkapIds->isEmpty()) {
            $this->warn("No erkap_id found for year {$year}.");
            return self::SUCCESS;
        }

        $erkapIds->chunk(100)->each(function ($chunk) use ($year) {
            foreach ($chunk as $erkapId) {
                SyncErkapTimelineYear::dispatch((int)$erkapId, $year)->onQueue('default');
            }
        });

        $this->info("Dispatched ".count($erkapIds)." timeline sync jobs for year {$year}.");
        return self::SUCCESS;
    }
}
