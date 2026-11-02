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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ScanTextModerationItemJob implements ShouldQueue
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

    public function handle(TextModerationScanService $scanner): void
    {
        $scanner->process($this->itemId);
    }

    public function failed(?Throwable $exception): void
    {
        $item = TextModerationItem::query()->find($this->itemId);

        if (!$item || in_array($item->scan_status, [
            TextModerationItem::STATUS_COMPLETED,
            TextModerationItem::STATUS_SKIPPED,
            TextModerationItem::STATUS_QUOTA_PAUSED,
            TextModerationItem::STATUS_ACTION_REQUIRED,
        ], true)) {
            return;
        }

        app(ModerationQuotaService::class)->consumeInterruptedReservation($item);
        $item->update([
            'scan_status' => TextModerationItem::STATUS_FAILED,
            'error_type' => $item->error_type ?: 'job_failed',
            'error_message' => $item->error_message
                ?: 'The queue exhausted its retries. This text item can be retried by an administrator.',
            'retryable' => true,
            'failed_at' => now(),
        ]);

        app(ModerationRunProgressService::class)->recalculate($item->run_id);
    }
}
