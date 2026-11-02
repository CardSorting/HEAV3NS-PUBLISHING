<?php

namespace App\Jobs;

use App\Model\MediaModerationItem;
use App\Services\Moderation\ModerationRunProgressService;
use App\Services\Moderation\ModerationScanService;
use App\Services\Moderation\ModerationQuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ScanMediaModerationItemJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 100;

    public int $timeout = 240;

    public function __construct(public int $itemId)
    {
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('media-moderation-provider'))
                ->shared()
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(ModerationScanService $scanner): void
    {
        $scanner->process($this->itemId);
    }

    public function failed(?Throwable $exception): void
    {
        $item = MediaModerationItem::query()->find($this->itemId);

        if (!$item || in_array($item->scan_status, [
            MediaModerationItem::STATUS_COMPLETED,
            MediaModerationItem::STATUS_AWAITING_PROVIDER,
            MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
            MediaModerationItem::STATUS_SKIPPED,
            MediaModerationItem::STATUS_QUOTA_PAUSED,
            MediaModerationItem::STATUS_ACTION_REQUIRED,
        ], true)) {
            return;
        }

        app(ModerationQuotaService::class)->consumeInterruptedReservation($item);

        $item->update([
            'scan_status' => MediaModerationItem::STATUS_FAILED,
            'error_type' => $item->error_type ?: 'job_failed',
            'error_message' => $item->error_message
                ?: 'The queue exhausted its retries. This item can be retried by an administrator.',
            'retryable' => true,
            'failed_at' => now(),
        ]);

        app(ModerationRunProgressService::class)->recalculate($item->run_id);
    }
}
