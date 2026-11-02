<?php

namespace App\Jobs;

use App\Model\QueueHealthCheck;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class QueueHealthCheckJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $healthCheckId)
    {
    }

    public function handle(): void
    {
        $healthCheck = QueueHealthCheck::query()->find($this->healthCheckId);

        if (!$healthCheck) {
            return;
        }

        $healthCheck->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        $healthCheck->update([
            'status' => 'completed',
            'completed_at' => now(),
            'error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        QueueHealthCheck::query()
            ->whereKey($this->healthCheckId)
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error' => $exception?->getMessage(),
            ]);
    }
}
