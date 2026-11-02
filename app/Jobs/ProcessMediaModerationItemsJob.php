<?php

namespace App\Jobs;

use App\Model\MediaModerationItem;
use App\Services\Moderation\ModerationQuotaService;
use App\Services\Moderation\ModerationRunProgressService;
use App\Services\Moderation\ModerationScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessMediaModerationItemsJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public array $itemIds)
    {
    }

    public function handle(ModerationScanService $scanner): void
    {
        foreach ($this->itemIds as $itemId) {
            $scanner->process((int) $itemId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $runIds = [];

        foreach (MediaModerationItem::query()->whereIn('id', $this->itemIds)->get() as $item) {
            if (in_array($item->scan_status, [
                MediaModerationItem::STATUS_COMPLETED,
                MediaModerationItem::STATUS_AWAITING_PROVIDER,
                MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
                MediaModerationItem::STATUS_SKIPPED,
                MediaModerationItem::STATUS_QUOTA_PAUSED,
                MediaModerationItem::STATUS_ACTION_REQUIRED,
            ], true)) {
                continue;
            }

            app(ModerationQuotaService::class)->consumeInterruptedReservation($item);
            $item->update([
                'scan_status' => MediaModerationItem::STATUS_FAILED,
                'error_type' => 'process_failed',
                'error_message' => 'The workerless scan process ended unexpectedly. This item can be retried.',
                'retryable' => true,
                'failed_at' => now(),
            ]);
            $runIds[$item->run_id] = true;
        }

        foreach (array_keys($runIds) as $runId) {
            app(ModerationRunProgressService::class)->recalculate($runId);
        }
    }
}
