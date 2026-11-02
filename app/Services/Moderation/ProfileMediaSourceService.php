<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;
use App\Model\User;
use App\Providers\GenericHelperServiceProvider;
use App\Services\Moderation\Data\ModerationInput;
use App\Services\Moderation\Data\ProfileMediaCandidate;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfileMediaSourceService
{
    /**
     * @return Generator<int, ProfileMediaCandidate>
     */
    public function candidates(
        array $scope,
        string $provider,
        string $modelSignature,
        string $policySignature,
    ): Generator {
        if (!in_array('image', $scope['media_types'] ?? [], true)) {
            return;
        }

        $sourceTypes = $this->selectedSourceTypes($scope);

        foreach ($sourceTypes as $sourceType) {
            $field = $this->field($sourceType);
            $disk = $this->activeDisk();
            $query = User::query()
                ->select(['id', $field, 'updated_at'])
                ->whereNotNull($field)
                ->where($field, '!=', '')
                ->whereNotIn($field, $this->defaultPaths($sourceType))
                ->orderBy('id');

            if (!($scope['force_rescan'] ?? false)) {
                $query->whereNotExists(function ($query) use (
                    $field,
                    $disk,
                    $sourceType,
                    $provider,
                    $modelSignature,
                    $policySignature,
                ): void {
                    $query->selectRaw('1')
                        ->from('media_moderation_items as prior_scan')
                        ->where('prior_scan.source_type', $sourceType)
                        ->whereColumn('prior_scan.source_id', 'users.id')
                        ->whereColumn('prior_scan.source_path', 'users.'.$field)
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

            foreach ($query->lazyById(500) as $user) {
                $path = trim((string) $user->getRawOriginal($field), '/');

                if ($path === '') {
                    continue;
                }

                yield new ProfileMediaCandidate(
                    userId: (int) $user->id,
                    sourceType: $sourceType,
                    path: $path,
                    disk: $disk,
                    updatedAt: $user->updated_at,
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    public function selectedSourceTypes(array $scope): array
    {
        $sources = $scope['sources'] ?? [];
        $types = [];

        if (in_array(ModerationAttachmentQuery::SOURCE_PROFILE_AVATARS, $sources, true)) {
            $types[] = MediaModerationItem::SOURCE_PROFILE_AVATAR;
        }

        if (in_array(ModerationAttachmentQuery::SOURCE_PROFILE_COVERS, $sources, true)) {
            $types[] = MediaModerationItem::SOURCE_PROFILE_COVER;
        }

        return $types;
    }

    public function isCurrent(MediaModerationItem $item): bool
    {
        if (!$item->isProfileAsset() || !$item->owner_user_id || !$item->source_path) {
            return false;
        }

        $field = $this->field($item->source_type);
        $user = User::query()->select(['id', $field])->find($item->owner_user_id);

        return $user
            && hash_equals(
                trim((string) $item->source_path, '/'),
                trim((string) $user->getRawOriginal($field), '/'),
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
                'The profile image could not be opened from its storage provider.',
                errorType: 'media_storage_error',
                retryable: true,
                previous: $exception,
            );
        }

        if (!is_resource($stream)) {
            throw new ModerationProviderException(
                'The profile image is missing or unreadable.',
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
            // Storage metadata is best-effort; the stable source snapshot below remains usable.
        }

        $size = null;
        $lastModified = null;

        try {
            $size = Storage::disk($disk)->size($path);
            $lastModified = Storage::disk($disk)->lastModified($path);
        } catch (Throwable) {
            // Some storage adapters do not expose metadata for profile images.
        }

        return hash('sha256', implode('|', [
            (string) $item->source_type,
            (string) $item->source_id,
            $disk,
            $path,
            (string) $size,
            (string) $lastModified,
        ]));
    }

    public function previewUrl(MediaModerationItem $item): ?string
    {
        if (!$item->isProfileAsset() || !$item->source_path) {
            return null;
        }

        $path = trim((string) $item->source_path, '/');
        $disk = $item->source_disk ?: $this->activeDisk();

        try {
            if ($disk === $this->activeDisk()) {
                return GenericHelperServiceProvider::getFilePathByActiveStorageDriver($path);
            }

            return Storage::disk($disk)->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    public function removeCurrent(MediaModerationItem $item): void
    {
        if (!$item->isProfileAsset() || !$item->owner_user_id || !$item->source_path) {
            throw new \DomainException('This moderation item is not a profile image.');
        }

        $field = $this->field($item->source_type);
        $path = trim((string) $item->source_path, '/');
        $updated = DB::table('users')
            ->where('id', $item->owner_user_id)
            ->where($field, $path)
            ->update([
                $field => null,
                'updated_at' => now(),
            ]);

        if ($updated < 1) {
            throw new \DomainException('The user has replaced or removed this profile image. Refresh the moderation item before deleting it.');
        }

        try {
            Storage::disk($item->source_disk ?: $this->activeDisk())->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function field(string $sourceType): string
    {
        return match ($sourceType) {
            MediaModerationItem::SOURCE_PROFILE_AVATAR => 'avatar',
            MediaModerationItem::SOURCE_PROFILE_COVER => 'cover',
            default => throw new \InvalidArgumentException("Unsupported profile media source [{$sourceType}]."),
        };
    }

    protected function activeDisk(): string
    {
        return (string) (config('filesystems.defaultFilesystemDriver') ?: config('filesystems.default', 'public'));
    }

    /**
     * @return list<string>
     */
    protected function defaultPaths(string $sourceType): array
    {
        return match ($sourceType) {
            MediaModerationItem::SOURCE_PROFILE_AVATAR => [
                '/img/default-avatar.jpg',
                'img/default-avatar.jpg',
                'users/default.png',
            ],
            MediaModerationItem::SOURCE_PROFILE_COVER => [
                '/img/default-cover.png',
                'img/default-cover.png',
            ],
            default => [],
        };
    }
}
