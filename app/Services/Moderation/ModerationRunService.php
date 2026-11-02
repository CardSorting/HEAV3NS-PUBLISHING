<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use App\Providers\AttachmentServiceProvider;
use App\Services\Queue\QueueRuntimeService;
use App\Settings\ModerationSettings;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModerationRunService
{
    public function __construct(
        protected ModerationConfiguration $configuration,
        protected ModerationAttachmentQuery $attachments,
        protected ProfileMediaSourceService $profileMedia,
        protected StreamPosterSourceService $streamPosters,
        protected ModerationProviderManager $providers,
        protected ModerationQuotaService $quota,
        protected QueueRuntimeService $runtime,
        protected ModerationJobDispatcher $dispatcher,
        protected ModerationRunProgressService $progress,
    ) {
    }

    public function estimate(array $scope): array
    {
        $scope = $this->attachments->normalizeScope($scope);
        $provider = $this->providers->provider($this->configuration->provider());
        $models = $this->configuration->models();
        $policy = $this->configuration->policy();
        $query = $this->attachments->query(
            $scope,
            $this->configuration->provider(),
            $this->configuration->mediaModelSignatures(),
            $this->configuration->policySignature(),
        );
        $items = 0;
        $operations = 0;
        $images = 0;
        $videos = 0;

        foreach ($query->select(['id', 'filename', 'type', 'length'])->lazy(500) as $attachment) {
            $mediaType = in_array(
                strtolower((string) $attachment->type),
                AttachmentServiceProvider::getTypeByExtension('video'),
                true,
            ) ? 'video' : 'image';
            $descriptor = new Data\ModerationInput(
                mediaType: $mediaType,
                filename: basename((string) $attachment->filename),
                stream: null,
                durationSeconds: $attachment->length !== null ? (int) $attachment->length : null,
            );

            $items++;
            $operations += $provider->estimateOperations($descriptor, $models, $policy);
            $mediaType === 'video' ? $videos++ : $images++;
        }

        foreach ($this->profileMedia->candidates(
            $scope,
            $this->configuration->provider(),
            $this->configuration->modelSignature('image'),
            $this->configuration->policySignature(),
        ) as $candidate) {
            $descriptor = new Data\ModerationInput(
                mediaType: 'image',
                filename: $candidate->filename(),
                stream: null,
            );

            $items++;
            $images++;
            $operations += $provider->estimateOperations($descriptor, $models, $policy);
        }

        foreach ($this->streamPosters->candidates(
            $scope,
            $this->configuration->provider(),
            $this->configuration->modelSignature('image'),
            $this->configuration->policySignature(),
        ) as $candidate) {
            $descriptor = new Data\ModerationInput(
                mediaType: 'image',
                filename: $candidate->filename(),
                stream: null,
            );

            $items++;
            $images++;
            $operations += $provider->estimateOperations($descriptor, $models, $policy);
        }

        $usage = $this->quota->usage($this->configuration->provider());

        return array_merge($usage, [
            'scan_type' => MediaModerationRun::TYPE_MEDIA,
            'items' => $items,
            'images' => $images,
            'videos' => $videos,
            'estimated_operations' => $operations,
            'video_processing_mode' => $this->configuration->videoProcessingMode(),
            'video_processing_label' => match ($this->configuration->videoProcessingMode()) {
                ModerationSettings::VIDEO_PROCESSING_SYNC => 'Synchronous video processing',
                ModerationSettings::VIDEO_PROCESSING_ASYNC => 'Asynchronous video processing',
                default => 'Automatic video processing',
            },
            'max_concurrent_video_jobs' => $this->configuration->maxConcurrentVideoJobs(),
            'mode' => $this->runtime->mode(),
            'mode_label' => $this->runtime->modeLabel(),
            'mode_item_limit' => $this->configuration->itemLimitForMode($this->runtime->mode()),
            'daily_remaining' => max(0, $usage['daily_limit'] - $usage['daily_used'] - $usage['daily_reserved']),
            'monthly_remaining' => max(0, $usage['monthly_limit'] - $usage['monthly_used'] - $usage['monthly_reserved']),
        ]);
    }

    public function start(array $scope, ?int $initiatedBy, ?int $requestedItems = null, ?int $requestedOperations = null): MediaModerationRun
    {
        if (!$this->configuration->enabled()) {
            throw new DomainException('Moderation must be enabled and configured before a media scan can start.');
        }

        $scope = $this->attachments->normalizeScope($scope);

        if ($scope['sources'] === [] || $scope['media_types'] === []) {
            throw new DomainException('Select at least one content source and media type before starting a media scan.');
        }

        if (in_array('video', $scope['media_types'], true)
            && !($this->configuration->policy()['video_enabled'] ?? false)) {
            throw new DomainException('Video moderation is currently disabled in moderation settings.');
        }

        $estimate = $this->estimate($scope);
        $itemLimit = $this->configuration->itemLimitForMode($this->runtime->mode());
        $maxItems = min($itemLimit, max(1, $requestedItems ?? $itemLimit));
        $availableOperations = min($estimate['daily_remaining'], $estimate['monthly_remaining']);
        $maxOperations = min(
            max(1, $requestedOperations ?? max(1, $availableOperations)),
            max(1, $availableOperations),
        );

        if ($estimate['items'] < 1) {
            throw new DomainException('No matching unscanned media was found.');
        }

        $run = DB::transaction(function () use ($scope, $initiatedBy, $maxItems, $maxOperations) {
            return MediaModerationRun::query()->create([
                'id' => (string) Str::uuid(),
                'initiated_by' => $initiatedBy,
                'scan_type' => MediaModerationRun::TYPE_MEDIA,
                'provider' => $this->configuration->provider(),
                'model_signature' => $this->configuration->modelSignature(),
                'models' => $this->configuration->models(),
                'scope' => $scope,
                'policy' => $this->configuration->policy(),
                'status' => MediaModerationRun::STATUS_QUEUED,
                'execution_mode' => $this->runtime->mode(),
                'connection' => $this->runtime->connection(),
                'queue' => $this->runtime->queueName(),
                'max_items' => $maxItems,
                'max_operations' => $maxOperations,
                'last_activity_at' => now(),
            ]);
        });

        $this->dispatcher->prepare($run);

        return $run->refresh();
    }

    public function cancel(MediaModerationRun $run): MediaModerationRun
    {
        if ($run->isTerminal()) {
            return $run;
        }

        $run->update(['cancel_requested_at' => now(), 'last_activity_at' => now()]);
        $run->items()
            ->where('scan_status', MediaModerationItem::STATUS_AWAITING_PROVIDER)
            ->get()
            ->each(fn (MediaModerationItem $item) => $this->quota->consumeInterruptedReservation($item));
        $run->items()
            ->whereIn('scan_status', [
                MediaModerationItem::STATUS_QUEUED,
                MediaModerationItem::STATUS_AWAITING_PROVIDER,
                MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
                MediaModerationItem::STATUS_RETRYING,
                MediaModerationItem::STATUS_QUOTA_PAUSED,
                MediaModerationItem::STATUS_ACTION_REQUIRED,
            ])
            ->update([
                'scan_status' => MediaModerationItem::STATUS_SKIPPED,
                'error_type' => 'cancelled',
                'error_message' => 'The scan was cancelled by an administrator.',
                'provider_next_poll_at' => null,
                'updated_at' => now(),
            ]);

        return $this->progress->recalculate($run) ?? $run->refresh();
    }

    public function resume(MediaModerationRun $run, bool $includeFailed = false): MediaModerationRun
    {
        if ($run->status === MediaModerationRun::STATUS_CANCELLED) {
            throw new DomainException('A cancelled scan cannot be resumed.');
        }

        $run->update([
            'status' => MediaModerationRun::STATUS_RUNNING,
            'completed_at' => null,
            'failed_at' => null,
            'error' => null,
            'last_activity_at' => now(),
        ]);

        $items = $run->items()
            ->where(function ($query) use ($includeFailed) {
                $query->where('scan_status', MediaModerationItem::STATUS_QUOTA_PAUSED);

                if ($includeFailed) {
                    $query->orWhere(function ($query) {
                        $query->where('scan_status', MediaModerationItem::STATUS_FAILED)
                            ->where('retryable', true);
                    });
                }
            })
            ->get();
        $run->items()->whereIn('id', $items->modelKeys())->update([
            'scan_status' => MediaModerationItem::STATUS_QUEUED,
            'error_type' => null,
            'error_code' => null,
            'error_message' => null,
            'provider_request_id' => null,
            'provider_media_id' => null,
            'provider_processing_mode' => null,
            'provider_progress' => null,
            'provider_submitted_at' => null,
            'provider_last_update_at' => null,
            'provider_poll_attempts' => 0,
            'provider_next_poll_at' => null,
            'provider_poll_error' => null,
            'failed_at' => null,
            'updated_at' => now(),
        ]);

        $this->dispatcher->items($run, $items->modelKeys());

        return $this->progress->recalculate($run) ?? $run->refresh();
    }
}
