<?php

namespace App\Jobs;

use App\Model\TextModerationItem;
use App\Services\Moderation\ModerationQuotaService;
use App\Services\Moderation\ModerationRunProgressService;
use App\Services\Moderation\TextModerationScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessTextModerationItemsJob implements ShouldQueue
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

    public function handle(TextModerationScanService $scanner): void
    {
        foreach ($this->itemIds as $itemId) {
            $scanner->process((int) $itemId);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $runIds = [];

        foreach (TextModerationItem::query()->whereIn('id', $this->itemIds)->get() as $item) {
            if (in_array($item->scan_status, [
                TextModerationItem::STATUS_COMPLETED,
                TextModerationItem::STATUS_SKIPPED,
                TextModerationItem::STATUS_QUOTA_PAUSED,
                TextModerationItem::STATUS_ACTION_REQUIRED,
            ], true)) {
                continue;
            }

            app(ModerationQuotaService::class)->consumeInterruptedReservation($item);
            $item->update([
                'scan_status' => TextModerationItem::STATUS_FAILED,
                'error_type' => 'process_failed',
                'error_message' => 'The workerless text scan ended unexpectedly. This item can be retried.',
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
