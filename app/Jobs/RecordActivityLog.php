<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecordActivityLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array<string, mixed>
     */
    protected array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        try {
            ActivityLog::create($this->data);
        } catch (\Throwable $exception) {
            Log::warning('activity_log_failed', [
                'message' => $exception->getMessage(),
                'request_id' => $this->data['request_id'] ?? null,
                'action' => $this->data['action'] ?? null,
                'table' => $this->data['table'] ?? null,
            ]);
        }
    }
}
