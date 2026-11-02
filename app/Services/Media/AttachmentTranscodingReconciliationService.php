<?php

namespace App\Services\Media;

use App\Model\AttachmentTranscoding;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AttachmentTranscodingReconciliationService
{
    public const NON_DURABLE_STALE_AFTER_MINUTES = 30;

    public function __construct(protected AttachmentTranscodingNotifier $notifier)
    {
    }

    public function shouldRun(): bool
    {
        try {
            return Schema::hasTable('attachment_transcodings')
                && $this->staleQuery()->exists();
        } catch (Throwable $exception) {
            return false;
        }
    }

    public function reconcile(int $limit = 50): int
    {
        $reconciled = 0;

        $this->staleQuery()
            ->limit(max(1, min(250, $limit)))
            ->get()
            ->each(function (AttachmentTranscoding $transcoding) use (&$reconciled): void {
                $updated = AttachmentTranscoding::query()
                    ->whereKey($transcoding->attachment_id)
                    ->whereIn('status', [
                        AttachmentTranscoding::STATUS_QUEUED,
                        AttachmentTranscoding::STATUS_PROCESSING,
                    ])
                    ->update([
                        'status' => AttachmentTranscoding::STATUS_FAILED,
                        'error_code' => 'processing_interrupted',
                        'error_message' => 'Video processing stopped before it could finish.',
                        'failed_at' => now(),
                        'updated_at' => now(),
                    ]);

                if ($updated) {
                    $reconciled++;
                    $this->notifier->notify($transcoding->refresh());
                }
            });

        return $reconciled;
    }

    protected function staleQuery()
    {
        return AttachmentTranscoding::query()
            ->whereIn('status', [
                AttachmentTranscoding::STATUS_QUEUED,
                AttachmentTranscoding::STATUS_PROCESSING,
            ])
            ->where(function ($query): void {
                $query
                    ->where(function ($local): void {
                        $local
                            ->where('driver', AttachmentTranscoding::DRIVER_FFMPEG)
                            ->where('execution_mode', '!=', 'worker')
                            ->where('updated_at', '<=', now()->subMinutes(self::NON_DURABLE_STALE_AFTER_MINUTES));
                    })
                    ->orWhere(function ($provider): void {
                        $provider
                            ->where('driver', AttachmentTranscoding::DRIVER_COCONUT)
                            ->where('updated_at', '<=', now()->subDay());
                    });
            })
            ->orderBy('updated_at');
    }
}
