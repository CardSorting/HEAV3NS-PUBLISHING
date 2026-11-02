<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\MediaModerationRun;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ModerationProviderCapacityService
{
    public function __construct(
        protected ModerationConfiguration $configuration,
        protected ModerationJobDispatcher $dispatcher,
    ) {
    }

    public function acquire(MediaModerationItem $item): bool
    {
        if ($item->media_type !== 'video') {
            return true;
        }

        return (bool) Cache::lock($this->lockName($item->provider), 30)->block(10, function () use ($item): bool {
            $item->refresh();

            if ($item->provider_processing_mode === 'async') {
                return true;
            }

            if ($this->activeCount($item->provider) >= $this->configuration->maxConcurrentVideoJobs()) {
                return false;
            }

            $item->update(['provider_processing_mode' => 'async']);

            return true;
        });
    }

    public function wait(MediaModerationItem $item, ?string $providerCode = null): void
    {
        $limit = $this->configuration->maxConcurrentVideoJobs();
        $message = $providerCode === '37'
            ? 'Sightengine reports that all simultaneous video slots are in use. Confirm that Maximum concurrent video jobs matches the provider plan; this video will continue automatically when a slot becomes available.'
            : "Waiting for a provider video slot. The configured limit is {$limit} simultaneous video ".($limit === 1 ? 'job.' : 'jobs.');
        $item->update([
            'scan_status' => MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
            'provider_processing_mode' => $item->provider_processing_mode ?: 'async',
            'provider_request_id' => null,
            'provider_media_id' => null,
            'provider_progress' => null,
            'provider_submitted_at' => null,
            'provider_next_poll_at' => null,
            'error_type' => 'provider_capacity',
            'error_code' => $providerCode,
            'error_message' => $message,
            'operations_reserved' => 0,
            'operation_reservation_date' => null,
            'retryable' => true,
            'failed_at' => null,
        ]);
    }

    public function releaseAvailable(?string $provider = null): int
    {
        $provider ??= $this->configuration->provider();
        /** @var Collection<int, MediaModerationItem> $items */
        $items = Cache::lock($this->lockName($provider), 30)->block(10, function () use ($provider): Collection {
            $available = max(
                0,
                $this->configuration->maxConcurrentVideoJobs() - $this->activeCount($provider),
            );

            if ($available < 1) {
                return new Collection();
            }

            $items = MediaModerationItem::query()
                ->with('run')
                ->where('provider', $provider)
                ->where('media_type', 'video')
                ->where('scan_status', MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY)
                ->whereHas('run', fn ($query) => $query
                    ->whereNull('cancel_requested_at')
                    ->whereNotIn('status', [
                        MediaModerationRun::STATUS_COMPLETED,
                        MediaModerationRun::STATUS_COMPLETED_WITH_ERRORS,
                        MediaModerationRun::STATUS_CANCELLED,
                        MediaModerationRun::STATUS_FAILED,
                    ]))
                ->orderBy('queued_at')
                ->orderBy('id')
                ->limit($available)
                ->get();

            foreach ($items as $item) {
                $item->update([
                    'scan_status' => MediaModerationItem::STATUS_QUEUED,
                    'error_type' => null,
                    'error_code' => null,
                    'error_message' => null,
                    'retryable' => false,
                ]);
            }

            return $items;
        });

        foreach ($items as $item) {
            if ($item->run) {
                $this->dispatcher->item($item->run, $item);
            }
        }

        return $items->count();
    }

    protected function activeCount(string $provider): int
    {
        return MediaModerationItem::query()
            ->where('provider', $provider)
            ->where('media_type', 'video')
            ->where('provider_processing_mode', 'async')
            ->whereIn('scan_status', [
                MediaModerationItem::STATUS_QUEUED,
                MediaModerationItem::STATUS_PROCESSING,
                MediaModerationItem::STATUS_AWAITING_PROVIDER,
            ])
            ->count();
    }

    protected function lockName(string $provider): string
    {
        return 'media-moderation-provider-capacity:'.$provider;
    }
}
