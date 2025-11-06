<?php

namespace App\Jobs;

use App\Services\ErkapTimelineService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncErkapTimelineYear implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $erkapId, public int $year) {}

    public function handle(ErkapTimelineService $svc): void
    {
        $svc->fetchYear($this->erkapId, $this->year);
    }
}
