<?php

namespace App\Jobs;

use App\Model\AttachmentTranscoding;
use App\Services\Media\AttachmentTranscodingNotifier;
use App\Services\Media\AttachmentTranscodingProcessor;
use App\Services\Media\AttachmentTranscodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAttachmentTranscodingJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 3;

    public int $timeout = AttachmentTranscodingService::JOB_TIMEOUT_SECONDS;

    public bool $failOnTimeout = true;

    public function __construct(public string $attachmentId)
    {
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('attachment-transcoding-'.$this->attachmentId))
                ->releaseAfter(30)
                ->expireAfter(AttachmentTranscodingService::QUEUE_RETRY_AFTER_SECONDS),
        ];
    }

    public function handle(
        AttachmentTranscodingProcessor $processor,
        AttachmentTranscodingNotifier $notifier,
    ): void {
        $lastPublishedProgress = -20;

        $processor->process($this->attachmentId, function ($progress) use ($notifier, &$lastPublishedProgress): void {
            $progress = max(0, min(100, (int) $progress));
            $coarseProgress = min(80, (int) floor($progress / 20) * 20);

            if ($coarseProgress <= $lastPublishedProgress) {
                return;
            }

            $lastPublishedProgress = $coarseProgress;
            $transcoding = AttachmentTranscoding::query()->find($this->attachmentId);
            if ($transcoding) {
                $notifier->notify($transcoding, $coarseProgress);
            }
        });

        $transcoding = AttachmentTranscoding::query()->find($this->attachmentId);
        if ($transcoding && $transcoding->status === AttachmentTranscoding::STATUS_COMPLETED) {
            $notifier->notify($transcoding, 100);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $transcoding = AttachmentTranscoding::query()->find($this->attachmentId);

        if (!$transcoding || in_array($transcoding->status, [
            AttachmentTranscoding::STATUS_COMPLETED,
            AttachmentTranscoding::STATUS_CANCELLED,
        ], true)) {
            return;
        }

        $transcoding->update([
            'status' => AttachmentTranscoding::STATUS_FAILED,
            'error_code' => $transcoding->error_code ?: 'job_failed',
            'error_message' => $transcoding->error_message
                ?: mb_substr($exception?->getMessage() ?: 'Video processing ended unexpectedly.', 0, 2000),
            'failed_at' => now(),
        ]);

        app(AttachmentTranscodingNotifier::class)->notify($transcoding->refresh());
    }
}
