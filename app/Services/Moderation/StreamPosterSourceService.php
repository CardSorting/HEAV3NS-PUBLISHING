<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\Stream;
use App\Providers\GenericHelperServiceProvider;
use App\Services\Moderation\Data\ModerationInput;
use App\Services\Moderation\Data\StreamPosterCandidate;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use DomainException;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StreamPosterSourceService
{
    /**
     * @return Generator<int, StreamPosterCandidate>
     */
    public function candidates(
        array $scope,
        string $provider,
        string $modelSignature,
        string $policySignature,
    ): Generator {
        if (!in_array('image', $scope['media_types'] ?? [], true)
            || !in_array(ModerationAttachmentQuery::SOURCE_STREAM_POSTERS, $scope['sources'] ?? [], true)) {
            return;
        }

        $disk = $this->activeDisk();
        $query = Stream::query()
            ->select(['id', 'user_id', 'poster', 'updated_at'])
            ->whereNotNull('poster')
            ->where('poster', '!=', '')
            ->whereNotIn('poster', ['/img/live-stream-cover.svg', 'img/live-stream-cover.svg'])
            ->orderBy('id');

        if (!($scope['force_rescan'] ?? false)) {
            $query->whereNotExists(function ($query) use (
                $disk,
                $provider,
                $modelSignature,
                $policySignature,
            ): void {
                $query->selectRaw('1')
                    ->from('media_moderation_items as prior_scan')
                    ->where('prior_scan.source_type', MediaModerationItem::SOURCE_STREAM_POSTER)
                    ->whereColumn('prior_scan.source_id', 'streams.id')
                    ->whereColumn('prior_scan.source_path', 'streams.poster')
                    ->where('prior_scan.source_disk', $disk)
                    ->where('prior_scan.provider', $provider)
                    ->where('prior_scan.model_signature', $modelSignature)
                    ->where('prior_scan.policy_signature', $policySignature)
                    ->where(function ($query): void {
                        $query->where('prior_scan.scan_status', MediaModerationItem::STATUS_COMPLETED)
                            ->orWhere(function ($query): void {
                                $query->where('prior_scan.scan_status', MediaModerationItem::STATUS_FAILED)
                                    ->where('prior_scan.retryable', false);
                            })
                            ->orWhere(function ($query): void {
                                $query->where('prior_scan.scan_status', MediaModerationItem::STATUS_SKIPPED)
                                    ->where(function ($query): void {
                                        $query->whereNull('prior_scan.error_type')
                                            ->orWhere('prior_scan.error_type', '!=', 'cancelled');
                                    });
                            });
                    });
            });
        }

        foreach ($query->lazyById(500) as $stream) {
            $path = trim((string) $stream->getRawOriginal('poster'), '/');

            if ($path === '') {
                continue;
            }

            yield new StreamPosterCandidate(
                streamId: (int) $stream->id,
                userId: (int) $stream->user_id,
                path: $path,
                disk: $disk,
                updatedAt: $stream->updated_at,
            );
        }
    }

    public function isCurrent(MediaModerationItem $item): bool
    {
        if (!$item->isStreamPoster() || !$item->source_id || !$item->source_path) {
            return false;
        }

        $stream = Stream::query()->select(['id', 'poster'])->find($item->source_id);

        return $stream
            && hash_equals(
                trim((string) $item->source_path, '/'),
                trim((string) $stream->getRawOriginal('poster'), '/'),
            );
    }

    public function descriptor(MediaModerationItem $item): ModerationInput
    {
        return new ModerationInput(
            mediaType: 'image',
            filename: basename((string) $item->source_path),
            stream: null,
        );
    }

    public function input(MediaModerationItem $item): ModerationInput
    {
        $descriptor = $this->descriptor($item);
        $disk = $item->source_disk ?: $this->activeDisk();
        $path = trim((string) $item->source_path, '/');

        try {
            $stream = Storage::disk($disk)->readStream($path);
        } catch (Throwable $exception) {
            throw new ModerationProviderException(
                'The stream poster could not be opened from its storage provider.',
                errorType: 'media_storage_error',
                retryable: true,
                previous: $exception,
            );
        }

        if (!is_resource($stream)) {
            throw new ModerationProviderException(
                'The stream poster is missing or unreadable.',
                errorType: 'media_missing',
            );
        }

        return new ModerationInput(
            mediaType: $descriptor->mediaType,
            filename: $descriptor->filename,
            stream: $stream,
        );
    }

    public function fingerprint(MediaModerationItem $item): string
    {
        $disk = $item->source_disk ?: $this->activeDisk();
        $path = trim((string) $item->source_path, '/');

        try {
            $checksum = Storage::disk($disk)->checksum($path);

            if (is_string($checksum) && $checksum !== '') {
                return hash('sha256', $disk.'|'.$checksum);
            }
        } catch (Throwable) {
            // Storage metadata is best-effort; source identity remains a stable fallback.
        }

        return hash('sha256', implode('|', [
            MediaModerationItem::SOURCE_STREAM_POSTER,
            (string) $item->source_id,
            $disk,
            $path,
        ]));
    }

    public function previewUrl(MediaModerationItem $item): ?string
    {
        if (!$item->isStreamPoster() || !$item->source_path) {
            return null;
        }

        $path = trim((string) $item->source_path, '/');
        $disk = $item->source_disk ?: $this->activeDisk();

        try {
            return $disk === $this->activeDisk()
                ? GenericHelperServiceProvider::getFilePathByActiveStorageDriver($path)
                : Storage::disk($disk)->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    public function removeCurrent(MediaModerationItem $item): void
    {
        if (!$item->isStreamPoster() || !$item->source_id || !$item->source_path) {
            throw new DomainException('This moderation item is not a stream poster.');
        }

        $path = trim((string) $item->source_path, '/');
        $updated = DB::table('streams')
            ->where('id', $item->source_id)
            ->where('poster', $path)
            ->update([
                'poster' => null,
                'updated_at' => now(),
            ]);

        if ($updated < 1) {
            throw new DomainException('The stream poster was replaced or removed. Refresh the moderation item before deleting it.');
        }

        try {
            Storage::disk($item->source_disk ?: $this->activeDisk())->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    protected function activeDisk(): string
    {
        return (string) (config('filesystems.defaultFilesystemDriver') ?: config('filesystems.default', 'public'));
    }
}
