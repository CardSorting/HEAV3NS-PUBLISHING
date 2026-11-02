<?php

namespace App\Jobs;

use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Providers\AttachmentServiceProvider;
use App\Services\Moderation\AttachmentMediaService;
use App\Services\Moderation\ModerationAttachmentQuery;
use App\Services\Moderation\ModerationConfiguration;
use App\Services\Moderation\ModerationJobDispatcher;
use App\Services\Moderation\ModerationQuotaService;
use App\Services\Moderation\ModerationRunProgressService;
use App\Services\Moderation\ModerationScanService;
use App\Services\Moderation\ModerationSignatureService;
use App\Services\Moderation\ProfileMediaSourceService;
use App\Services\Moderation\StreamPosterSourceService;
use App\Services\Queue\QueueRuntimeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class PrepareMediaModerationRunJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public string $runId)
    {
    }

    public function handle(
        ModerationAttachmentQuery $attachments,
        AttachmentMediaService $media,
        ProfileMediaSourceService $profileMedia,
        StreamPosterSourceService $streamPosters,
        ModerationSignatureService $signatures,
        ModerationJobDispatcher $dispatcher,
        ModerationRunProgressService $progress,
        ModerationScanService $scanner,
    ): void {
        $run = MediaModerationRun::query()->find($this->runId);

        if (!$run || !$run->isMedia() || $run->isTerminal() || $run->cancel_requested_at) {
            return;
        }

        $run->update([
            'status' => MediaModerationRun::STATUS_PREPARING,
            'started_at' => $run->started_at ?? now(),
            'last_activity_at' => now(),
            'error' => null,
        ]);

        $policySignature = $signatures->policy($run->policy);
        $videoInterval = (float) ($run->policy['video_effective_interval_seconds']
            ?? $run->policy['video_interval_seconds']
            ?? ModerationConfiguration::DEFAULT_VIDEO_INTERVAL_SECONDS);
        $itemModelSignatures = [
            'image' => $signatures->model($run->provider, $run->models, $videoInterval, 'image'),
            'video' => $signatures->model($run->provider, $run->models, $videoInterval, 'video'),
        ];
        $candidates = $attachments->query($run->scope, $run->provider, $itemModelSignatures, $policySignature)
            ->limit($run->max_items)
            ->get();
        $itemIds = [];

        foreach ($candidates as $attachment) {
            $subject = $media->subject($attachment);
            $mediaType = $media->mediaType($attachment);

            if (!isset($itemModelSignatures[$mediaType])) {
                continue;
            }

            $item = MediaModerationItem::query()->firstOrCreate(
                [
                    'run_id' => $run->id,
                    'source_type' => MediaModerationItem::SOURCE_ATTACHMENT,
                    'source_id' => (string) $attachment->id,
                ],
                [
                    'attachment_id' => $attachment->id,
                    'source_path' => $attachment->filename,
                    'source_disk' => AttachmentServiceProvider::getStorageProviderName($attachment->driver),
                    'owner_user_id' => $media->ownerId($attachment),
                    'subject_type' => $subject['type'],
                    'subject_id' => $subject['id'],
                    'media_type' => $mediaType,
                    'provider' => $run->provider,
                    'model_signature' => $itemModelSignatures[$mediaType],
                    'policy_signature' => $policySignature,
                    'source_updated_at' => $attachment->updated_at,
                    'scan_status' => MediaModerationItem::STATUS_QUEUED,
                    'queued_at' => now(),
                ],
            );
            $itemIds[] = $item->id;
        }

        $remaining = max(0, $run->max_items - count($itemIds));

        if ($remaining > 0) {
            foreach ($profileMedia->candidates(
                $run->scope,
                $run->provider,
                $itemModelSignatures['image'],
                $policySignature,
            ) as $candidate) {
                $item = MediaModerationItem::query()->firstOrCreate(
                    [
                        'run_id' => $run->id,
                        'source_type' => $candidate->sourceType,
                        'source_id' => $candidate->sourceId(),
                    ],
                    [
                        'source_path' => $candidate->path,
                        'source_disk' => $candidate->disk,
                        'owner_user_id' => $candidate->userId,
                        'subject_type' => $candidate->sourceType,
                        'subject_id' => $candidate->userId,
                        'media_type' => 'image',
                        'provider' => $run->provider,
                        'model_signature' => $itemModelSignatures['image'],
                        'policy_signature' => $policySignature,
                        'fingerprint' => $candidate->fingerprint(),
                        'source_updated_at' => $candidate->updatedAt,
                        'scan_status' => MediaModerationItem::STATUS_QUEUED,
                        'queued_at' => now(),
                    ],
                );
                $itemIds[] = $item->id;
                $remaining--;

                if ($remaining < 1) {
                    break;
                }
            }
        }

        if ($remaining > 0) {
            foreach ($streamPosters->candidates(
                $run->scope,
                $run->provider,
                $itemModelSignatures['image'],
                $policySignature,
            ) as $candidate) {
                $item = MediaModerationItem::query()->firstOrCreate(
                    [
                        'run_id' => $run->id,
                        'source_type' => MediaModerationItem::SOURCE_STREAM_POSTER,
                        'source_id' => $candidate->sourceId(),
                    ],
                    [
                        'source_path' => $candidate->path,
                        'source_disk' => $candidate->disk,
                        'owner_user_id' => $candidate->userId,
                        'subject_type' => 'stream',
                        'subject_id' => $candidate->streamId,
                        'media_type' => 'image',
                        'provider' => $run->provider,
                        'model_signature' => $itemModelSignatures['image'],
                        'policy_signature' => $policySignature,
                        'fingerprint' => $candidate->fingerprint(),
                        'source_updated_at' => $candidate->updatedAt,
                        'scan_status' => MediaModerationItem::STATUS_QUEUED,
                        'queued_at' => now(),
                    ],
                );
                $itemIds[] = $item->id;
                $remaining--;

                if ($remaining < 1) {
                    break;
                }
            }
        }

        if ($itemIds === []) {
            $run->update([
                'status' => MediaModerationRun::STATUS_COMPLETED,
                'total_items' => 0,
                'completed_at' => now(),
                'last_activity_at' => now(),
            ]);

            return;
        }

        $run->update([
            'status' => MediaModerationRun::STATUS_RUNNING,
            'total_items' => count($itemIds),
            'last_activity_at' => now(),
        ]);

        foreach ($itemIds as $itemId) {
            if ($run->execution_mode === QueueRuntimeService::MODE_WORKER) {
                $dispatcher->item($run, $itemId);
            } else {
                $scanner->process($itemId);
            }
        }

        $progress->recalculate($run);
    }

    public function failed(?Throwable $exception): void
    {
        $run = MediaModerationRun::query()->find($this->runId);

        if (!$run || !$run->isMedia()) {
            return;
        }

        $run->items()
            ->where('scan_status', MediaModerationItem::STATUS_PROCESSING)
            ->get()
            ->each(fn (MediaModerationItem $item) => app(ModerationQuotaService::class)
                ->consumeInterruptedReservation($item));

        $run->items()
            ->whereIn('scan_status', [
                MediaModerationItem::STATUS_QUEUED,
                MediaModerationItem::STATUS_PROCESSING,
                MediaModerationItem::STATUS_RETRYING,
            ])
            ->update([
                'scan_status' => MediaModerationItem::STATUS_FAILED,
                'error_type' => 'preparation_failed',
                'error_message' => 'The scan process ended unexpectedly. This item can be retried.',
                'retryable' => true,
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

        app(ModerationRunProgressService::class)->recalculate($run);

        $run->update([
            'status' => MediaModerationRun::STATUS_FAILED,
            'failed_at' => now(),
            'last_activity_at' => now(),
            'error' => $exception ? mb_substr($exception->getMessage(), 0, 2000) : 'The scan could not be prepared.',
        ]);
    }
}
